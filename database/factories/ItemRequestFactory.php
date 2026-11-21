<?php

namespace Database\Factories;

use App\Models\Division;
use App\Models\ItemRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemRequest>
 */
class ItemRequestFactory extends Factory
{
    protected $model = ItemRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requester_name' => fake()->name(),
            'division_id' => Division::factory(),
            'note' => fake()->optional()->sentence(),
            'status' => ItemRequest::STATUS_PENDING,
        ];
    }
}
