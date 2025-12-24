<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'company_name',
        'phone' // 'balance' removed
    ];

    public function purchaseBills()
    {
        return $this->hasMany(PurchaseBill::class);
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function getBalanceAttribute()
    {
        // Calculate total purchases
        $totalPurchases = \App\Models\PurchaseItem::whereHas('purchaseBill', function ($q) {
            $q->where('supplier_id', $this->id);
        })->selectRaw('sum(quantity * cost_price) as total')->value('total') ?? 0;

        $totalPayments = $this->payments()->sum('amount');

        return $totalPurchases - $totalPayments;
    }

//         public function getBalanceAttribute()
// {
//     // 1. Calculate total purchases (What you owe them)
//     $totalPurchases = \App\Models\PurchaseItem::whereHas('purchaseBill', function ($q) {
//         $q->where('supplier_id', $this->id);
//     })
//     // FIX: Change 'unit_price' to 'cost_price'
//     ->selectRaw('sum(quantity * cost_price) as total')
//     ->value('total') ?? 0;

//     // 2. Calculate total payments (What you paid them)
//     $totalPayments = $this->payments()->sum('amount');

//     // Supplier balance: Total Bills - Total Paid
//     return $totalPurchases - $totalPayments;
// }
}
