<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'name', 'company_name', 'phone', 'balance'
    ];

    public function purchaseBills()
    {
        return $this->hasMany(PurchaseBill::class);
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }
}
