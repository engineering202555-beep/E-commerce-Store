<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;


    protected $fillable = [
        'status',
        'notes',
        'favourite_id',
        'order_type',
        'quantity',
        'in_order_id',
        'delivery_order_id',
        'menu_item_id',
        'chef_id'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];


    public function inOrder()
    {
        return $this->belongsTo(InOrder::class);
    }
    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class);
    }
    public function chef()
    {
        return $this->belongsTo(Chef::class);
    }
    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function favourite()
    {
        return $this->belongsTo(Favourite::class);
    }
}
