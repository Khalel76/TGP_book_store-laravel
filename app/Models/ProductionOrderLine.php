<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionOrderLine extends Model
{
    protected $fillable = [
        'production_order_id', 'product_id', 'quantity_used'
    ];

    public function productionOrder()
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function product() // المادة الخام
    {
        return $this->belongsTo(Product::class);
    }
}
