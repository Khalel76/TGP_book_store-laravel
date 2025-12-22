<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillOfMaterial extends Model
{
    protected $fillable = [
        'name', 'product_id'
    ];

    public function product() // المنتج النهائي المتوقع
    {
        return $this->belongsTo(Product::class);
    }

    public function details() // المكونات
    {
        return $this->hasMany(BillOfMaterialDetail::class);
    }
}
