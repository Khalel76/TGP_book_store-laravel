<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    //
    protected $fillable = [
        'user_id',
        'payment_method_id',
        'address',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function totalCart()
    {
        $total = 0;
        foreach($this->items as $item){
            $total = $total + $item->totalItem();
        }
        return $total;
    }
}
