<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionOrder extends Model
{
    protected $fillable = [
        'date', 'is_completed',
        'product_id', 'quantity_to_produce'
    ];

    public function product() // المنتج الذي يتم تصنيعه
    {
        return $this->belongsTo(Product::class);
    }

    public function consumedItems() // المواد المستهلكة
    {
        return $this->hasMany(ProductionOrderLine::class);
    }
}
