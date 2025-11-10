<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $guarded;

    public $timestamps = true;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    protected static function booted()
    {
        static::created(function ($transaction) {
            $user = $transaction->user()->first();

            if ($user) {
                // Ensure wallet exists
                $wallet = $user->wallet ?? $user->wallet()->create(['balance' => 0]);

                $wallet->recalculateBalance();
            }
        });

        static::deleted(function ($transaction) {
            $user = $transaction->user()->first();

            if ($user && $user->wallet) {
                $user->wallet->recalculateBalance();
            }
        });

        static::created(fn($tx) => self::updateMonthlySummary($tx->user_id));
        static::updated(fn($tx) => self::updateMonthlySummary($tx->user_id));
        static::deleted(fn($tx) => self::updateMonthlySummary($tx->user_id));
    }

    public function goal()
    {
        return $this->belongsTo(Goal::class);
    }


    public static function updateMonthlySummary($userId)
    {
        $month = now()->format('m');
        $year = now()->format('Y');

        $totalIncome = self::where('user_id', $userId)
            ->where('type', 'income')
            ->sum('amount');

        $totalExpense = self::where('user_id', $userId)
            ->where('type', 'expense')
            ->sum('amount');

        $profit = $totalIncome - $totalExpense;

        BalanceSummary::updateOrCreate(
            ['user_id' => $userId, 'month' => $month, 'year' => $year],
            [
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'profit' => $profit,
            ]
        );

    }
}
