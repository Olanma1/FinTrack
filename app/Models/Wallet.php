<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $guarded;

    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recalculateBalance()
    {
        $income = $this->user->transactions()->where('type', 'income')->sum('amount');
        $expense = $this->user->transactions()->where('type', 'expense')->sum('amount');
        $this->balance = $income - $expense;
        $this->save();
    }
}
