<?php

namespace Database\Factories;

use App\Models\ItemType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemType>
 */
class ItemTypeFactory extends Factory
{
    protected $model = ItemType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'is_active' => true,
        ];
    }
}
