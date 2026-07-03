<?php

namespace Database\Factories;

use App\Models\TroveType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TroveType>
 */
class TroveTypeFactory extends Factory
{
    protected $model = TroveType::class;

    public function definition(): array
    {
        return [
            'label' => ['en' => Str::title($this->faker->unique()->words(2, true))],
        ];
    }
}
