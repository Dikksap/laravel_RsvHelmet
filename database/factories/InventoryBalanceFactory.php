<?php

namespace Database\Factories;

use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryBalance>
 */
class InventoryBalanceFactory extends Factory
{
    protected $model = InventoryBalance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'item_id' => Item::factory(),
            'quantity_on_hand' => fake()->randomFloat(3, 0, 1000),
            'minimum_quantity' => fake()->randomFloat(3, 0, 100),
            'last_counted_at' => fake()->optional()->dateTimeBetween('-1 month'),
        ];
    }
}
