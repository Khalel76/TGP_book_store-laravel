<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'sales_invoice_id', 'product_id',
        'quantity', 'unit_price'
    ];

    public function salesinvoice()
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    
}
