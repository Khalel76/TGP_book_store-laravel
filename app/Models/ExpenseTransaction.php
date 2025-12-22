<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseTransaction extends Model
{
    protected $fillable = [
        'treasury_id', 'expense_category_id',
        'amount', 'description', 'date'
    ];

    public function treasury()
    {
        return $this->belongsTo(Treasury::class);
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
