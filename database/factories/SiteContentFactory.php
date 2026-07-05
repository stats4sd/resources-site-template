<?php

namespace Database\Factories;

use App\Models\SiteContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteContent>
 */
class SiteContentFactory extends Factory
{
    protected $model = SiteContent::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(2),
            // value is JSON-translatable (Spatie): keyed by locale.
            'value' => ['en' => $this->faker->sentence()],
        ];
    }
}
