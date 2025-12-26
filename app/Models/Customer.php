<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'address',
    ];

    public function salesInvoices()
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function payments()
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function getBalanceAttribute()
    {
        // Calculate total sales
        $totalSales = \App\Models\InvoiceItem::whereHas('salesInvoice', function ($q) {
            $q->where('customer_id', $this->id);
        })->selectRaw('sum(quantity * unit_price) as total')->value('total') ?? 0;

        $totalPayments = $this->payments()->sum('amount');

        return $totalPayments - $totalSales ;
    }
}
