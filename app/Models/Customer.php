<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone_number',
        'location',
        'points_payment',
        'loyalty_points',
        'birthdate',
        'image',
        'no_show_count',
        'banned_until'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function deliveryOrders()
    {
        return $this->hasMany(Reservation::class);
    }

    public function favourites()
    {
        return $this->hasMany(Favourite::class);
    }

    public function menuFavoriteItems() {
        return $this->belongsToMany(MenuItem::class);
    }
}

