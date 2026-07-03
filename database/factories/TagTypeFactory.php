<?php

namespace Database\Factories;

use App\Models\TagType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TagType>
 */
class TagTypeFactory extends Factory
{
    protected $model = TagType::class;

    public function definition(): array
    {
        $label = rtrim($this->faker->unique()->words(2, true));

        return [
            'slug' => Str::slug($label).'-'.$this->faker->unique()->numberBetween(1, 999999),
            'label' => ['en' => Str::title($label)],
            'description' => ['en' => $this->faker->sentence()],
            'freetext' => false,
            'show_in_filter' => false,
            'use_custom_tag_order' => false,
            'order_column' => null,
        ];
    }

    /** Pin the slug (e.g. 'themes' / 'topics' for themeAndTopicTags tests). */
    public function slug(string $slug): static
    {
        return $this->state(fn () => ['slug' => $slug]);
    }

    public function shownInFilter(): static
    {
        return $this->state(fn () => ['show_in_filter' => true]);
    }
}
