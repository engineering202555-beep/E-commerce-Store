<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'status',
        'total_price',
        'delivery_address'
    ];

    public function orderItems(){
        return $this->hasMany(OrderItem::class);
    }
    public function review(){
        return $this->hasOne(Review::class);
    }

    public function customer(){
        return $this->belongsTo(Customer::class);
    }

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }


}
