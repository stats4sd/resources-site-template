<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Trove;
use App\Models\Type;
use App\Models\User;

class TroveFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Trove::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'slug' => $this->faker->slug,
            'description' => $this->faker->text,
            'uploader_id' => User::factory(),
            'creation_date' => $this->faker->date(),
            'type_id' => Type::factory(),
            'cover_image' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'public' => $this->faker->boolean,
            'youtube' => $this->faker->regexify('[A-Za-z0-9]{400}'),
            'source' => $this->faker->boolean,
            'download_count' => $this->faker->numberBetween(-10000, 10000),
        ];
    }
}
