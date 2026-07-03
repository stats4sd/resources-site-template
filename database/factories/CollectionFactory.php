<?php

namespace Database\Factories;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Collection>
 */
class CollectionFactory extends Factory
{
    protected $model = Collection::class;

    public function definition(): array
    {
        return [
            'title' => ['en' => rtrim($this->faker->unique()->sentence(4), '.')],
            'description' => ['en' => $this->faker->paragraph()],
            'uploader_id' => User::factory(),
            'public' => true,
        ];
    }

    /** A non-public collection. */
    public function private(): static
    {
        return $this->state(fn () => ['public' => false]);
    }
}
