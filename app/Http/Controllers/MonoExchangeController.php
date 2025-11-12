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

        $response = Http::withHeaders([
            'mono-sec-key' => env('MONO_SECRET_KEY'),
        ])->post('https://api.withmono.com/account/auth', [
            'code' => $validated['code'],
        ]);

        $data = $response->json();

        if (!isset($data['id'])) {
            return response()->json(['error' => 'Unable to link account'], 400);
        }

        // Save the Mono account ID to the user
        $user = $request->user();
        $user->update(['mono_account_id' => $data['id']]);

        return response()->json(['message' => 'Bank account linked successfully']);
    }

    /**
     * Fetch and import bank transactions into FinTrack
     */
    public function importTransactions(Request $request)
    {
        $user = $request->user();

        if (!$user->mono_account_id) {
            return response()->json(['error' => 'User has not linked a bank account'], 400);
        }

        $response = Http::withHeaders([
            'mono-sec-key' => env('MONO_SECRET_KEY'),
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
    $response = Http::withHeaders([
    'mono-sec-key' => "test_sk_zzx2uficff3vo61bo2dz",
    'Content-Type' => 'application/json',
])->post('https://api.withmono.com/v2/accounts/initiate', [
    // 'customer' => [
    //     'name' => $request->user()->name,
    //     'email' => $request->user()->email,
    // ],
    'meta' => [
        'ref' => uniqid('mono_'),
    ],
    'scope' => 'auth',
    'redirect_url' => 'https://fintrack-frontend.vercel.app/mono-callback',
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
        'data' => $response->json('data'),
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

}

