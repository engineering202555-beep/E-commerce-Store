<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    protected $fillable = [
        'title',
        'description',
        'offer_price',
        'starts_at',
        'ends_at'
    ];

    public function menuItems() {
        return $this->belongsToMany(MenuItem::class, 'menu_item_offer_pivots')
            ->withPivot('quantity', 'discounted_price');
    }

    public function deliveryOrders()
    {
        return $this->hasMany(DeliveryOrder::class);
    }

}
