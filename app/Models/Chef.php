<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chef extends Model
{
    protected $fillable = [
        'username',
        'user_id',
        'menu_category_id'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function reports()
    {
        return $this->hasMany(Report::class);
    }
    public function menuCategory()
    {
        return $this->belongsTo(MenuCategory::class);
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}











