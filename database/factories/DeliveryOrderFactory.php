<?php

namespace Database\Factories;

use App\Models\DeliveryOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeliveryOrder>
 */
class DeliveryOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = DeliveryOrder::class;
    public function definition()
    {

        return [
            'customer_id' => 1, // أو fake()->numberBetween(1, 100)
            'status' => 'pending',
            'delivery_address' => fake()->address(),
            'total_price' => fake()->numberBetween(1000, 50000),
        ];
    }
}
