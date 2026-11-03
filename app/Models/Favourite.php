<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Favourite extends Model
{
    protected $fillable = [

        'menu_item_id',
        'customer_id'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
