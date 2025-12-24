<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_bill_id',
         'product_id' ,
         'quantity',
         'cost_price'
    ];
    public function purchaseBill()
    {
        return $this->belongsTo(PurchaseBill::class, 'purchase_bill_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
