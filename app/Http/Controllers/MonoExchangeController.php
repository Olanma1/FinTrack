<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\BankLinkedMail;
use App\Mail\BankUnlinkedMail;


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

         Mail::to($user->email)->send(new BankLinkedMail($user));

        return response()->json(['message' => 'Bank account linked successfully']);
    }

    /**
     * Fetch and import bank transactions into FinTrack
     */
    // public function importTransactions(Request $request)
    // {
    //     $user = $request->user();

    //     $secret = config('services.mono.secret_key');

    //     if (!$secret) {
    //         Log::error('Mono secret key is missing from configuration');
    //         return response()->json(['error' => 'Server configuration error'], 500);
    //     }

    //     if (!$user->mono_account_id) {
    //         return response()->json(['error' => 'User has not linked a bank account'], 400);
    //     }

    //     $response = Http::withHeaders([
    //         'mono-sec-key' => $secret,
    //     ])->get("https://api.withmono.com/accounts/{$user->mono_account_id}/transactions?limit=100");

    //     if (!$response->successful()) {
    //         return response()->json(['error' => 'Unable to fetch transactions'], 500);
    //     }

    //     $transactions = $response->json()['data'] ?? [];

    //     foreach ($transactions as $tx) {
    //         Transaction::updateOrCreate(
    //             [
    //                 'mono_transaction_id' => $tx['_id'],
    //                 'user_id' => $user->id,
    //             ],
    //             [
    //                 'type' => $tx['amount'] < 0 ? 'expense' : 'income',
    //                 'amount' => abs($tx['amount']),
    //                 'note' => $tx['narration'] ?? 'Bank transaction',
    //                 'category_id' => null, // you can later map automatically
    //                 'date' => $tx['date'],
    //                 'source' => 'mono',
    //             ]
    //         );
    //     }

    //     return response()->json(['message' => 'Bank transactions synced successfully']);
    // }

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

    public function importTransactions(Request $request)
    {
        $account = $request->user();

        if (!$account || !$account->mono_account_id) {
            return response()->json(['message' => 'No linked Mono account found.'], 404);
        }

        try {
            $page = 1;
            $perPage = 50; // Fetch 50 transactions per request
            $totalFetched = 0;

            do {
                $response = Http::withHeaders([
                    'mono-sec-key' => config('services.mono.secret_key'),
                    'accept' => 'application/json',
                ])->get("https://api.withmono.com/v2/accounts/{$account->mono_account_id}/transactions", [
                    'page' => $page,
                    'perPage' => $perPage, // limit per request
                ]);

                if ($response->failed()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Failed to fetch transactions',
                        'details' => $response->json(),
                    ], $response->status());
                }

                $data = $response->json()['data'] ?? [];

                if (empty($data)) {
                    break; 
                }

                $transactionsToInsert = [];

                foreach ($data as $txn) {
                    $date = isset($txn['date']) ? Carbon::parse($txn['date'])->toDateTimeString() : now();

                    $category = null;
                    if (!empty($txn['category']) && $txn['category'] !== 'unknown') {
                        $category = Category::firstOrCreate(
                            ['name' => $txn['category']],
                            ['description' => ucfirst(str_replace('_', ' ', $txn['category']))]
                        );
                    }

                    $type = match ($txn['type'] ?? 'other') {
                        'debit' => 'expense',
                        'credit' => 'income',
                        default => 'other',
                    };

                    $transactionsToInsert[] = [
                        'user_id' => $account->id,
                        'mono_transaction_id' => $txn['id'],
                        'narration' => $txn['narration'] ?? 'No description',
                        'amount' => $txn['amount'] ?? 0,
                        'type' => $type,
                        'balance' => $txn['balance'] ?? 0,
                        'date' => $date,
                        'category_id' => $category?->id,
                        'source' => 'mono',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // Bulk insert or update
                Transaction::upsert(
                    $transactionsToInsert,
                    ['mono_transaction_id'], // unique key
                    ['amount', 'type', 'balance', 'date', 'category_id', 'narration', 'updated_at']
                );

                $totalFetched += count($data);
                $page++;

            } while (count($data) === $perPage); // Continue if full page returned

            return response()->json([
                'status' => 'success',
                'message' => "Transactions synced successfully",
                'total_synced' => $totalFetched,
            ]);

        } catch (\Exception $e) {
            Log::error('Mono Transaction Fetch Error', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
    public function handle(Request $request)
    {
        Log::info("🔥 Mono Webhook Received", $request->all());

        $secret = config('services.mono.webhook_secret');

        if (!$this->verifySignature($request, $secret)) {
            Log::warning("❌ Invalid Mono Webhook Signature");
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        if ($event === "transactions.added") {
            return $this->importNewTransactions($data);
        }

        return response()->json(['message' => 'Ignored'], 200);
    }

    private function verifySignature(Request $request, $secret)
    {
        $signature = $request->header('mono-signature');
        $hash = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($signature, $hash);
    }

    private function importNewTransactions($data)
    {
        $accountId = $data['accountId'];

        // 🔄 Trigger a fresh sync
        Http::withToken(config('services.mono.secret_key'))
            ->post("https://api.withmono.com/accounts/{$accountId}/sync");

        $response = Http::withToken(config('services.mono.secret_key'))
            ->get("https://api.withmono.com/accounts/{$accountId}/transactions");

        $transactions = $response->json()['data'] ?? [];

        foreach ($transactions as $tx) {

            if (Transaction::where('mono_transaction_id', $tx['_id'])->exists()) {
                continue;
            }

            // Insert new transaction
            Transaction::create([
                'mono_transaction_id' => $tx['_id'],
                'user_id' => $this->getUserIdFromAccount($accountId),
                'narration' => $tx['narration'] ?? null,
                'amount' => $tx['amount'] * 100, 
                'type' => $tx['type'] === "credit" ? "income" : "expense",
                'balance' => $tx['balance'] ?? 0,
                'date' => Carbon::parse($tx['date'])->toDateTimeString(),
                'source' => 'mono',
                'category_id' => null,
            ]);
        }

        Log::info("✅ New Mono transactions imported");

        return response()->json(['message' => 'ok'], 200);
    }

    private function getUserIdFromAccount($accountId)
    {
        return User::where('mono_account_id', $accountId)->value('id');
    }

    public function unlink(Request $request)
    {
        $user = $request->user();

        if (!$user->mono_account_id) {
            return response()->json(['error' => 'No linked bank account'], 400);
        }

        try {
            Http::withHeaders([
                'mono-sec-key' => config('services.mono.secret_key'),
            ])->post("https://api.withmono.com/v2/accounts/{$user->mono_account_id}/unlink");

        } catch (\Exception $e) {
            // Even if Mono unlink fails, still remove locally
            Log::error("Mono unlink failed", ['error' => $e->getMessage()]);
        }

        // 🔥 Remove Mono account from user
        $user->update(['mono_account_id' => null]);

        Mail::to($user->email)->send(new BankUnlinkedMail($user));

        return response()->json(['message' => 'Bank account unlinked successfully']);
    }


}
