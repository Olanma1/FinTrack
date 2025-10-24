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
    }

}
