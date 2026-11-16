<?php

namespace Database\Factories;

use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => InventoryMovement::TYPE_IN_PURCHASE,
            'item_id' => Item::factory(),
            'warehouse_id' => Warehouse::factory(),
            'quantity' => fake()->randomFloat(3, 1, 100),
            'production_batch_id' => null,
            'transfer_group_id' => null,
            'occurred_at' => now(),
            'reference' => fake()->optional()->bothify('REF-####'),
            'note' => null,
            'created_by' => null,
        ];
    }
}
