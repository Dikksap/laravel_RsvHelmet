<?php

namespace Database\Factories;

use App\Models\ItemAttribute;
use App\Models\ItemAttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemAttributeValue>
 */
class ItemAttributeValueFactory extends Factory
{
    protected $model = ItemAttributeValue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attribute_id' => ItemAttribute::factory(),
            'value' => fake()->unique()->word(),
            'code' => strtoupper(fake()->unique()->lexify('??')),
        ];
    }
}
