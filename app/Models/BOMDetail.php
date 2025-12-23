<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BOMDetail extends Model
{
    protected $table = 'b_o_m_details';

    protected $fillable = [
        'bill_of_material_id',
        'product_id',
        'quantity'
    ];

    public function billOfMaterial()
    {
        return $this->belongsTo(BillOfMaterial::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
