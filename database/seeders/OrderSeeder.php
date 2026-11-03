<?php

namespace Database\Seeders;

use App\Models\DeliveryOrder;
use App\Models\InOrder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create in-restaurant orders
        $inOrders = [
            [
                'waiter_id' => 1,
                'table_layout_id' => 1,
                'status' => 'done',
                'total_price' => 37.98,
                'payment_completed' => true
            ],
            [
                'waiter_id' => 2,
                'table_layout_id' => 2,
                'status' => 'pending',
                'total_price' => 24.99,
                'payment_completed' => false
            ],
        ];

        foreach ($inOrders as $orderData) {
            InOrder::create($orderData);
        }

        // Create delivery orders
        $deliveryOrders = [
            [
                'delivery_address' => '123 Main St',
                'status' => 'done',
                'total_price' => 12.99,
                'customer_id' => $customerIds[0] ?? 1,
            ],
            [
                'delivery_address' => '456 Oak Ave',
                'status' => 'pending',
                'total_price' => 8.99,
                'customer_id' => $customerIds[1] ?? 2,            ],
        ];

        foreach ($deliveryOrders as $orderData) {
            DeliveryOrder::create($orderData);
        }

    }
}
