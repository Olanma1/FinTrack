<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class InsightController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();

        $income = $user->transactions()->where('type', 'income')->sum('amount');
        $expenses = $user->transactions()->where('type', 'expense')->sum('amount');
        $topCategories = $user->transactions()
            ->selectRaw('category_id, SUM(amount) as total')
            ->where('type', 'expense')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->with('category')
            ->take(5)
            ->get();

        $goals = $user->goals->map(fn($g) => [
            'name' => $g->name,
            'progress' => $g->progress(),
        ]);

        return response()->json([
            'total_income' => $income,
            'total_expenses' => $expenses,
            'net_balance' => $income - $expenses,
            'top_spending_categories' => $topCategories,
            'goals' => $goals,
        ]);
    }
}
