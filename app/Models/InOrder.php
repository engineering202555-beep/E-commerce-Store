<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InOrder extends Model
{
    protected $fillable = [
        'status',
        'total_price',
        'payment_completed',
        'table_layout_id',
        'waiter_id'
    ];

    public function tableLayout()
    {
        return $this->belongsTo(TableLayout::class);
    }
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function waiter()
    {
        return $this->belongsTo(Waiter::class);
    }
}
