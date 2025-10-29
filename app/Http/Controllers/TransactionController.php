<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $transactions = $request->user()->transactions()
            ->with('category')
            ->latest('date')
            ->paginate(10);

        return response()->json($transactions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTransactionRequest $request)
    {
        $validated = $request->validated();

        $validated['date'] = $validated['date'] ?? now();
        $transaction = $request->user()->transactions()->create($validated);
        if ($transaction->user->wallet) {
            $transaction->user->wallet->recalculateBalance();
        }
        $transaction->load('category');

        return response()->json($transaction, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Transaction $transaction)
    {
        // $this->authorize('view', $transaction);
        $transaction->load('category');
        return response()->json($transaction);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreTransactionRequest $request, Transaction $transaction)
    {
        //  $this->authorize('update', $transaction);
        $transaction->update($request->validated());
        $transaction->user->wallet->recalculateBalance();
        $transaction->load('category');
        return response()->json($transaction);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transaction $transaction)
    {
        // $this->authorize('delete', $transaction);
        if ($transaction->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $transaction->delete();
        $transaction->user->wallet->recalculateBalance();

        return response()->json(['message' => 'Transaction deleted']);
    }
}
