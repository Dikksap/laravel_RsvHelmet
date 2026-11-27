<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'recipient_name' => fake()->company(),
            'delivery_date' => now()->toDateString(),
            'status' => Delivery::STATUS_DRAFT,
            'note' => fake()->optional()->sentence(),
            'created_by' => null,
        ];
    }
}
