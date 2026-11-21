<?php

namespace Database\Factories;

use App\Models\StockOpname;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockOpname>
 */
class StockOpnameFactory extends Factory
{
    protected $model = StockOpname::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'OPNAME-'.now()->format('YmdHis').'-'.fake()->unique()->numerify('####'),
            'warehouse_id' => Warehouse::factory(),
            'status' => StockOpname::STATUS_DRAFT,
            'note' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
            'completed_at' => null,
            'completed_by' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StockOpname::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StockOpname::STATUS_CANCELLED,
        ]);
    }
}
