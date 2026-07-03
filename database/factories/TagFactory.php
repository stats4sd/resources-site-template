<?php

namespace Database\Factories;

use App\Models\Tag;
use App\Models\TagType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        return [
            'name' => ['en' => Str::title($this->faker->unique()->words(2, true))],
            'type_id' => TagType::factory(),
            'order_column' => null,
        ];
    }

    public function ofType(TagType $type): static
    {
        return $this->state(fn () => ['type_id' => $type->id]);
    }
}
