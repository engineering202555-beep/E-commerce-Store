<?php

namespace Database\Factories;

use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = OrderItem::class;

    public function definition()
    {
        return [
            'menu_item_id' => fake()->numberBetween(1, 8),
            'quantity' => fake()->numberBetween(1, 5),
            'status' => 'pending',
            'order_type' => 'delivery',
            'notes' => fake()->sentence(),
        ];
    }
}
