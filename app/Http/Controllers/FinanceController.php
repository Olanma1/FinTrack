<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;

class FinanceController extends Controller
{
    public function balanceSummary(Request $request)
    {
        $user = $request->user();

        $transactions = Transaction::where('user_id', $user->id)->get();

        $total_income = $transactions->where('type', 'income')->sum('amount');
        $total_expense = $transactions->where('type', 'expense')->sum('amount');
        $profit = $total_income - $total_expense;

        $currentMonth = now()->month;
        $lastMonth = now()->subMonth()->month;

        $currentMonthIncome = $transactions
            ->where('type', 'income')
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        $lastMonthIncome = Transaction::where('user_id', $user->id)
            ->where('type', 'income')
            ->whereMonth('created_at', $lastMonth)
            ->sum('amount');

        $performance = $lastMonthIncome > 0
            ? round((($currentMonthIncome - $lastMonthIncome) / $lastMonthIncome) * 100, 2)
            : null;

        // ✅ Build monthly breakdown for charts (last 6 months)
        $monthlyReport = Transaction::selectRaw('MONTH(created_at) as month, type, SUM(amount) as total')
            ->where('user_id', $user->id)
            ->whereYear('created_at', now()->year)
            ->groupBy('month', 'type')
            ->orderBy('month')
            ->get()
            ->groupBy('month')
            ->map(function ($items, $month) {
                $income = $items->where('type', 'income')->sum('total');
                $expense = $items->where('type', 'expense')->sum('total');
                return [
                    'month' => date('M', mktime(0, 0, 0, $month, 1)),
                    'income' => (float) $income,
                    'expense' => (float) $expense,
                ];
            })
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => [
                    'total_income' => $total_income,
                    'total_expense' => $total_expense,
                    'profit' => $profit,
                    'performance' => $performance,
                ],
                'monthly_report' => $monthlyReport,
            ],
        ]);
    }

}
