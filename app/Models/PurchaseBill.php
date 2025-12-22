<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseBill extends Model
{
    protected $fillable = [
        'date', 'invoice_number', 'supplier_id', 'total_amount'
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }
}
