<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ProductionBatch;
use App\Models\ProductionBatchItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionBatchItem>
 */
class ProductionBatchItemFactory extends Factory
{
    protected $model = ProductionBatchItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'production_batch_id' => ProductionBatch::factory(),
            'item_id' => Item::factory(),
            'quantity_planned' => fake()->randomFloat(3, 10, 5000),
        ];
    }
}
