<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Treasury extends Model
{
    protected $fillable = [
        'name', 'current_balance'
    ];

    // يمكن إضافة علاقات للمدفوعات والمقبوضات هنا إن لزم الأمر للتقارير
}
