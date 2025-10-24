<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $guarded;

      public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function spent()
    {
        return $this->user->transactions()
            ->where('category_id', $this->category_id)
            ->where('type', 'expense')
            ->whereBetween('date', [$this->start_date, $this->end_date])
            ->sum('amount');
    }

    public function progress()
    {
        $spent = $this->spent();
        return [
            'spent' => $spent,
            'remaining' => max(0, $this->amount - $spent),
            'percent_used' => round(($spent / $this->amount) * 100, 1)
        ];
    }
}
