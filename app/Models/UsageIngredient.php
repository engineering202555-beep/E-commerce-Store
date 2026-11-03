<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageIngredient extends Model
{
    protected $fillable = [
        'quantity_required_per_meal',
        'menu_item_id',
        'supply_id'
    ];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
    public function supply()
    {
        return $this->belongsTo(Supply::class);
    }
}
