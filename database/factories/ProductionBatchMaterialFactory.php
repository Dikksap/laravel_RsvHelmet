<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ProductionBatch;
use App\Models\ProductionBatchMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionBatchMaterial>
 */
class ProductionBatchMaterialFactory extends Factory
{
    protected $model = ProductionBatchMaterial::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'production_batch_id' => ProductionBatch::factory(),
            'item_id' => Item::factory(),
            'quantity_required' => fake()->randomFloat(3, 10, 5000),
        ];
    }
}
