<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierPayment extends Model
{
    protected $fillable = [
        'supplier_id',
        'treasury_id',
        'amount',
        'date'
    ];

    protected $casts = [
        'date' => 'datetime'
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function treasury()
    {
        return $this->belongsTo(Treasury::class);
    }
}
