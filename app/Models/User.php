<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'password',
        'type',
        'status'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    public function author()
    {
        return $this->hasOne(Author::class);
    }

    public function customer()
    {
        return $this->hasOne(Customer::class);
    }

    public function books()
    {
        return $this->belongsToMany(Book::class);
    }


    public function approve()
    {
        $this->status = 'approve';
        $this->save();
    }

    public function block()
    {
        $this->status = 'block';
        $this->save();
    }


    // public function translateStatus()
    // {
    //     if($this->status == 'approve')
    //     {
    //         return 'تم القبول';
    //     }
    //     return $this->status;
    // }
}
