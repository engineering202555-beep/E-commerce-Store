<?php

namespace Database\Seeders;

use App\Models\OrderItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $orderItems = [
            [
                'menu_item_id' => 1,
                'in_order_id' => 1,
                'delivery_order_id' => null,
                'chef_id' => 1,
                'quantity' => 1,
                'status' => 'pending',
                'order_type' => 'in',
                'notes' => ''
            ],
            [
                'menu_item_id' => 2,
                'in_order_id' => 1,
                'delivery_order_id' => null,
                'chef_id' => 2,
                'quantity' => 1,
                'status' => 'done',
                'order_type' => 'in',
                'notes' => ''
            ],
            [
                'menu_item_id' => 2,
                'in_order_id' => 2,
                'delivery_order_id' => null,
                'chef_id' => 2,
                'quantity' => 1,
                'status' => 'pending',
                'order_type' => 'in',
                'notes' => ''
            ],
            [
                'menu_item_id' => 1,
                'in_order_id' => null,
                'delivery_order_id' => 1,
                'chef_id' => 1,
                'quantity' => 1,
                'status' => 'pending',
                'order_type' => 'delivery',
                'notes' => ''
            ],
            [
                'menu_item_id' => 3,
                'in_order_id' => null,
                'delivery_order_id' => 2,
                'chef_id' => 1,
                'quantity' => 1,
                'status' => 'pending',
                'order_type' => 'delivery',
                'notes' => ''
            ],
        ];

        foreach ($orderItems as $item) {
            OrderItem::create($item);
        }
    }
}
