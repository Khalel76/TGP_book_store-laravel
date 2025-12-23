<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'count_in_box',
        'metres_in_box'
    ];

    protected $casts = [
        'count_in_box' => 'integer',
        'metres_in_box' => 'float',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
