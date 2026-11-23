<?php

namespace Database\Factories;

use App\Models\ExternalItemMapping;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalItemMapping>
 */
class ExternalItemMappingFactory extends Factory
{
    protected $model = ExternalItemMapping::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'external_variant_id' => fake()->unique()->numberBetween(1, 10000),
            'external_variant_code' => 'WB'.fake()->numerify('###'),
            'external_product_name' => 'Windbreaker',
            'external_style_name' => fake()->randomElement(['Motif', 'Solid']),
            'external_color_name' => fake()->safeColorName(),
            'external_size_name' => fake()->randomElement(['MD', 'LG', 'XL', 'XXL']),
            'is_active' => true,
        ];
    }
}
