<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItemOfferPivot extends Model
{
    protected $fillable = [
        'discounted_price',
        'quantity',
        'menu_item_id',
        'offer_id'
    ];

}
