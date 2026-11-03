<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = [
        'title',
        'body',
        'chef_id',
        'waiter_id'
    ];

    public function chef()
    {
        return $this->belongsTo(Chef::class);
    }
    public function waiter()
    {
        return $this->belongsTo(Waiter::class);
    }

}
