<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemRequest;
use App\Models\ItemRequestLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemRequestLine>
 */
class ItemRequestLineFactory extends Factory
{
    protected $model = ItemRequestLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_request_id' => ItemRequest::factory(),
            'item_id' => Item::factory(),
            'quantity' => fake()->randomFloat(3, 0.001, 100),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
