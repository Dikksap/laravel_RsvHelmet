<?php

namespace Database\Factories;

use App\Models\ProductionBatch;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductionBatch>
 */
class ProductionBatchFactory extends Factory
{
    protected $model = ProductionBatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_code' => Str::upper(fake()->unique()->bothify('BATCH-####')),
            'status' => ProductionBatch::STATUS_DRAFT,
            'material_warehouse_id' => Warehouse::factory(),
            'output_warehouse_id' => Warehouse::factory(),
            'started_at' => null,
            'finished_at' => null,
            'notes' => null,
            'created_by' => null,
        ];
    }
}
