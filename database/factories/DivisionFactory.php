<?php

namespace Database\Factories;

use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Division>
 */
class DivisionFactory extends Factory
{
    protected $model = Division::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
        ];
    }
}
