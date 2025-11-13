<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

use function Illuminate\Log\log;

class MonoExchangeController extends Controller
{
   public function exchange(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        $secret = config('services.mono.secret_key');

        if (!$secret) {
            Log::error('Mono secret key is missing from configuration');
            return response()->json(['error' => 'Server configuration error'], 500);
        }

        $response = Http::withHeaders([
            'mono-sec-key' => $secret,
            'Content-Type' => 'application/json',
            'accept' => 'application/json',
        ])->post('https://api.withmono.com/v2/accounts/auth', [
            'code' => $validated['code'],
        ]);

        $data = $response->json();

        if ($response->failed() || !isset($data['data']['id'])) {
            Log::error('Mono exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return response()->json(['error' => 'Unable to link account'], 400);
        }

        $accountId = $data['data']['id'];
        $user = $request->user();
        $user->update(['mono_account_id' => $accountId]);

        return response()->json(['message' => 'Bank account linked successfully']);
    }

    /**
     * Fetch and import bank transactions into FinTrack
     */
    public function importTransactions(Request $request)
    {
        $user = $request->user();

        $secret = config('services.mono.secret_key');

        if (!$secret) {
            Log::error('Mono secret key is missing from configuration');
            return response()->json(['error' => 'Server configuration error'], 500);
        }

        if (!$user->mono_account_id) {
            return response()->json(['error' => 'User has not linked a bank account'], 400);
        }

        $response = Http::withHeaders([
            'mono-sec-key' => $secret,
        ])->get("https://api.withmono.com/accounts/{$user->mono_account_id}/transactions?limit=100");

        if (!$response->successful()) {
            return response()->json(['error' => 'Unable to fetch transactions'], 500);
        }

        $transactions = $response->json()['data'] ?? [];

        foreach ($transactions as $tx) {
            Transaction::updateOrCreate(
                [
                    'reference' => $tx['_id'],
                    'user_id' => $user->id,
                ],
                [
                    'type' => $tx['amount'] < 0 ? 'expense' : 'income',
                    'amount' => abs($tx['amount']),
                    'note' => $tx['narration'] ?? 'Bank transaction',
                    'category_id' => null, // you can later map automatically
                    'date' => $tx['date'],
                    'source' => 'mono',
                ]
            );
        }

        return response()->json(['message' => 'Bank transactions synced successfully']);
    }

    public function initiate(Request $request)
    {
        $user = $request->user();

        $frontendBaseUrl = rtrim(env('FRONTEND_BASE_URL', 'http://localhost:5173'), '/');
        $redirectUrl = $frontendBaseUrl . '/mono-callback';

        $response = Http::withHeaders([
            'mono-sec-key' => 'test_sk_zzx2uficff3vo61bo2dz',
            'Content-Type' => 'application/json',
        ])->post('https://api.withmono.com/v2/accounts/initiate', [
            'customer' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'meta' => [
                'ref' => uniqid('mono_'),
            ],
            'scope' => 'auth',
            'redirect_url' => $redirectUrl, 
        ]);

        if ($response->failed()) {
            return response()->json([
                'status' => 'failed',
                'error' => $response->json(),
            ], $response->status());
        }

        return response()->json([
            'status' => 'success',
            'mono_url' => $response->json('data.mono_url'),
        ]);
    }

    public function webhook(Request $request)
    {
        $payload = $request->all();

        if ($payload['event'] === 'account.linked') {
            $accountId = $payload['data']['id'];

            // Store the account ID for the user or trigger data fetch
             $user = $request->user();
            $user->update(['mono_account_id' => $accountId]);
        }

        return response()->json(['status' => 'ok']);
    }

    public function getTransactions(Request $request)
    {
        $account = $request->user();

        if (!$account || !$account->mono_account_id) {
            return response()->json(['message' => 'No linked Mono account found.'], 404);
        }

        $response = Http::withHeaders([
            'mono-sec-key' => config('services.mono.secret_key'),
            'accept' => 'application/json',
        ])->get("https://api.withmono.com/v2/accounts/{$account->mono_account_id}/transactions", [
            'paginate' => 'false', // ✅ fetch all results in one request
        ]);

        if ($response->failed()) {
            return response()->json([
                'message' => 'Failed to fetch transactions from Mono',
                'error' => $response->json(),
            ], $response->status());
        }

        foreach ($response['data'] as $txn) {
            $category = \App\Models\Category::where('name', $txn['category'])->first();
             $date = \Carbon\Carbon::parse($txn['date'])->toDateTimeString(); 
    Transaction::updateOrCreate(
        ['mono_transaction_id' => $txn['id']],
        [
            'user_id' => auth()->id(),
            'narration' => $txn['narration'],
            'amount' => $txn['amount'],
            'type' => $txn['type'] === 'debit' ? 'expense' : 'income',
            'balance' => $txn['balance'],
            'date' => $date,
            'category_id' => $category?->id,
            'source' => 'mono',
        ]
    );
        return response()->json([
            'status' => 'success',
            'data' => $response->json('data'),
        ]);
    }

}
}
