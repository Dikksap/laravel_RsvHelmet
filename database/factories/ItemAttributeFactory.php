<?php

namespace Database\Factories;

use App\Models\ItemAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemAttribute>
 */
class ItemAttributeFactory extends Factory
{
    protected $model = ItemAttribute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
