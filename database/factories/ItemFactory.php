<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Division;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemType;
use App\Models\Uom;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_item_id' => null,
            'has_variants' => false,
            'item_code' => Str::upper(fake()->unique()->bothify('ITM-####-???')),
            'barcode' => fake()->unique()->ean13(),
            'item_name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'item_type_id' => ItemType::factory(),
            'category_id' => null,
            'brand_id' => null,
            'division_id' => null,
            'base_uom_id' => Uom::factory(),
            'weight' => fake()->randomFloat(3, 0, 100),
            'weight_uom_id' => null,
            'length' => fake()->randomFloat(3, 0, 100),
            'width' => fake()->randomFloat(3, 0, 100),
            'height' => fake()->randomFloat(3, 0, 100),
            'dimension_uom_id' => null,
            'base_price' => fake()->randomFloat(2, 0, 1000000),
            'standard_cost' => fake()->randomFloat(2, 0, 1000000),
            'is_stockable' => true,
            'is_sellable' => true,
            'is_purchasable' => true,
            'is_active' => true,
        ];
    }

    public function withCategory(?ItemCategory $category = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'category_id' => $category?->getKey() ?? ItemCategory::factory(),
        ]);
    }

    public function withBrand(?Brand $brand = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'brand_id' => $brand?->getKey() ?? Brand::factory(),
        ]);
    }

    public function withDivision(?Division $division = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'division_id' => $division?->getKey() ?? Division::factory(),
        ]);
    }

    public function variantOf(Item $parent): static
    {
        return $this->state(fn (array $attributes): array => [
            'parent_item_id' => $parent->getKey(),
            'has_variants' => false,
        ]);
    }

    public function parent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'has_variants' => true,
            'parent_item_id' => null,
        ]);
    }
}
