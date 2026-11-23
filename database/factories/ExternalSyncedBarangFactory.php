<?php

namespace Database\Factories;

use App\Models\ExternalSyncedBarang;
use App\Models\Item;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalSyncedBarang>
 */
class ExternalSyncedBarangFactory extends Factory
{
    protected $model = ExternalSyncedBarang::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode_barang' => fake()->unique()->bothify('BC###-WB###-######-####'),
            'external_id' => fake()->unique()->numberBetween(1, 100000),
            'external_variant_id' => fake()->numberBetween(1, 1000),
            'external_batch_id' => fake()->numberBetween(1, 50),
            'item_id' => Item::factory(),
            'warehouse_id' => Warehouse::factory(),
            'inventory_movement_id' => null,
            'status' => 'FINISHGOOD',
            'external_occurred_at' => now()->subHours(2),
            'synced_at' => now(),
        ];
    }
}
