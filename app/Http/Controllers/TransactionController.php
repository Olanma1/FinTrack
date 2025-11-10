<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Goal; // Rename this to Target later if you migrate the table
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $transactions = $request->user()->transactions()
            ->with(['category', 'goal'])
            ->latest('date')
            ->paginate(10);

        return response()->json($transactions);
    }

    public function store(StoreTransactionRequest $request)
    {
        $validated = $request->validated();
        $validated['date'] = $validated['date'] ?? now();

        $transaction = $request->user()->transactions()->create($validated);

        // ✅ Wallet balance recalculation
        if ($transaction->user->wallet) {
            $transaction->user->wallet->recalculateBalance();
        }

        // ✅ Handle goal/target update
        if (!empty($validated['goal_id']) && $validated['type'] === 'income') {
            $goal = Goal::find($validated['goal_id']);
            if ($goal) {
                $goal->current_amount += $validated['amount'];

                if ($goal->current_amount >= $goal->target_amount) {
                    $goal->status = 'completed';
                }

                $goal->save();
            }
        }

        $transaction->load(['category', 'goal']);

        return response()->json([
            'message' => 'Transaction created successfully',
            'data' => [
                'transaction' => $transaction,
                'goal' => isset($goal) ? $goal : null,
            ],
        ], 201);
    }

    public function show(Transaction $transaction)
    {
        $transaction->load(['category', 'goal']);
        return response()->json($transaction);
    }

    public function update(StoreTransactionRequest $request, Transaction $transaction)
    {
        $validated = $request->validated();

        // ✅ Check owner
        if ($transaction->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // ✅ Adjust goal/target if amount/type changed
        if (!empty($validated['goal_id'])) {
            $goal = Goal::find($validated['goal_id']);
            if ($goal) {
                // If editing an existing transaction that was income, revert old value
                if ($transaction->type === 'income' && $transaction->goal_id) {
                    $oldGoal = Goal::find($transaction->goal_id);
                    if ($oldGoal) {
                        $oldGoal->current_amount -= $transaction->amount;
                        if ($oldGoal->current_amount < 0) $oldGoal->current_amount = 0;
                        $oldGoal->save();
                    }
                }

                // Apply new value if income
                if ($validated['type'] === 'income') {
                    $goal->current_amount += $validated['amount'];
                    if ($goal->current_amount >= $goal->target_amount) {
                        $goal->status = 'completed';
                    }
                    $goal->save();
                }
            }
        }

        $transaction->update($validated);

        if ($transaction->user->wallet) {
            $transaction->user->wallet->recalculateBalance();
        }

        $transaction->load(['category', 'goal']);

        return response()->json([
            'message' => 'Transaction updated successfully',
            'data' => [
                'transaction' => $transaction,
                'goal' => isset($goal) ? $goal : null,
            ],
        ]);
    }

    public function destroy(Transaction $transaction)
    {
        if ($transaction->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // ✅ Reverse goal contribution if deleting income transaction linked to a goal
        if ($transaction->goal_id && $transaction->type === 'income') {
            $goal = Goal::find($transaction->goal_id);
            if ($goal) {
                $goal->current_amount -= $transaction->amount;
                if ($goal->current_amount < 0) $goal->current_amount = 0;
                $goal->save();
            }
        }

        $transaction->delete();

        if ($transaction->user->wallet) {
            $transaction->user->wallet->recalculateBalance();
        }

        return response()->json(['message' => 'Transaction deleted successfully']);
    }
}
