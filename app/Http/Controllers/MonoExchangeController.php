<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Transaction;

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
}

