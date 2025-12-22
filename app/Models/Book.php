<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    //

    protected $fillable = [
        'title',
        'publish_year',
        'price',
        'isbn',
        'category_id',
        'qty',
        'owner_id',
    ];


    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function user()
    {
        return $this->belongsToMany(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function requestes()
    {
        return $this->hasMany(BookRequsestAuthor::class);
    }
}
