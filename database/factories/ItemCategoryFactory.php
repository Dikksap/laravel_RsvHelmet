<?php

namespace Database\Factories;

use App\Models\ItemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemCategory>
 */
class ItemCategoryFactory extends Factory
{
    protected $model = ItemCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'parent_id' => null,
            'is_active' => true,
        ];
    }

    public function withParent(?ItemCategory $parent = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'parent_id' => $parent?->getKey() ?? ItemCategory::factory(),
        ]);
    }
}
