<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Waiter extends Model
{
    protected $fillable = [
        'username',
        'user_id'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function inOrders()
    {
        return $this->hasMany(InOrder::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
}
