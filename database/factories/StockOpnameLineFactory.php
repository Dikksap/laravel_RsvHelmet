<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\StockOpname;
use App\Models\StockOpnameLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockOpnameLine>
 */
class StockOpnameLineFactory extends Factory
{
    protected $model = StockOpnameLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_opname_id' => StockOpname::factory(),
            'item_id' => Item::factory(),
            'system_quantity' => fake()->randomFloat(3, 0, 100),
            'counted_quantity' => null,
            'adjustment_quantity' => null,
            'counted_at' => null,
            'counted_by' => null,
            'note' => null,
        ];
    }
}
