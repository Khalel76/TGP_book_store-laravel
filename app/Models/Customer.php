<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name', 'phone', 'address', 'balance'
    ];

    public function invoices()
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function payments()
    {
        return $this->hasMany(CustomerPayment::class);
    }
}
