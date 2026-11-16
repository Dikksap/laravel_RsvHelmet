<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemComponent>
 */
class ItemComponentFactory extends Factory
{
    protected $model = ItemComponent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_item_id' => Item::factory(),
            'component_item_id' => Item::factory(),
            'quantity' => fake()->randomFloat(3, 0.5, 5),
            'note' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
