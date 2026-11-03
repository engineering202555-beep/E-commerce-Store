<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuCategory extends Model
{
    protected $fillable = [
        'type_name',
        'description'
    ];

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }

    public function chefs()
    {
        return $this->hasMany(Chef::class);
    }
}
