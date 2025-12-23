<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Treasury extends Model
{
    protected $fillable = [
        'name' // 'current_balance' removed
    ];

    public function customerPayments()
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function supplierPayments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function expenseTransactions()
    {
        return $this->hasMany(ExpenseTransaction::class);
    }

    public function getCurrentBalanceAttribute()
    {
        $income = $this->customerPayments()->sum('amount');
        $expenses = $this->supplierPayments()->sum('amount')
            + $this->expenseTransactions()->sum('amount');

        return $income - $expenses;
    }
}
