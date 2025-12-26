<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'name',
        'code',
        'type',
        'category_id',
        'quantity_in_stock',
        'cost_price',
        'selling_price',
        'is_sellable',
        'is_purchasable',
        'is_manufactured',
        'origin_country',
        'pattern',
        'thickness',
        'unit'
    ];

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function bomDetails()
    {
        return $this->hasMany(BOMDetail::class);
    }
}
