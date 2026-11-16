<?php

namespace Database\Factories;

use App\Models\Uom;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Uom>
 */
class UomFactory extends Factory
{
    protected $model = Uom::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('???')),
            'name' => fake()->unique()->word(),
            'is_active' => true,
        ];
    }
}
