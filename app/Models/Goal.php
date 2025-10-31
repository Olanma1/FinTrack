<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    protected $guarded;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function addProgress($amount)
    {
        $this->current_amount += $amount;
        if ($this->current_amount >= $this->target_amount) {
            $this->status = 'completed';
        }
        $this->save();
    }

    public function progress()
    {
        $percent = ($this->current_amount / $this->target_amount) * 100;
        return round(min(100, $percent), 1);
        $this->current_amount += $amount;
        $this->save();
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
