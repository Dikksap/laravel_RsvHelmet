<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\DeliveryLine;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryLine>
 */
class DeliveryLineFactory extends Factory
{
    protected $model = DeliveryLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_id' => Delivery::factory(),
            'item_id' => Item::factory(),
            'quantity' => fake()->randomFloat(3, 1, 20),
            'note' => null,
        ];
    }
}
