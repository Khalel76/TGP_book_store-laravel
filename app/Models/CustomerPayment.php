<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPayment extends Model
{
    protected $fillable = [
        'customer_id',
        'treasury_id',
        'amount',
        'date'
    ];

    protected $casts = [
        'date' => 'datetime'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function treasury()
    {
        return $this->belongsTo(Treasury::class);
    }
}
