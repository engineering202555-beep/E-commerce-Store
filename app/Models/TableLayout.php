<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableLayout extends Model
{
    protected $fillable = [
        'number_of_chairs',
        'status',
        'x_position',
        'y_position'
    ];

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function inOrders()
    {
        return $this->hasMany(InOrder::class);
    }
}
