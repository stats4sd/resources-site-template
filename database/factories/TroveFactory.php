<?php

namespace Database\Factories;

use App\Models\Trove;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trove>
 */
class TroveFactory extends Factory
{
    protected $model = Trove::class;

    public function definition(): array
    {
        // title/description are JSON-translatable (Spatie): assigning an array keyed
        // by locale stores one JSON blob. slug is intentionally omitted so the model's
        // `saving` hook generates it (use ->withSlug() to pin one).
        return [
            'title' => ['en' => rtrim($this->faker->unique()->sentence(4), '.')],
            'description' => ['en' => $this->faker->paragraph()],
            'trove_type_id' => null,
            'source' => $this->faker->boolean(),
            'creation_date' => $this->faker->date(),
            'uploader_id' => User::factory(),
            'external_links' => null,
            'video_links' => null,
            'published_at' => null,
        ];
    }

    /** Pin an explicit slug (bypasses the auto-generation hook). */
    public function withSlug(string $slug): static
    {
        return $this->state(fn () => ['slug' => $slug]);
    }

    /** A published canonical row. */
    public function published(): static
    {
        return $this->state(fn () => ['published_at' => now()]);
    }

    /** A shadow draft of the given canonical row (published_id set, published_at null). */
    public function draftOf(Trove $canonical): static
    {
        return $this->state(fn () => [
            'published_id' => $canonical->id,
            'published_at' => null,
        ]);
    }

    /** An outstanding review request (in review, not yet reviewed). */
    public function inReview(): static
    {
        return $this->state(fn () => [
            'review_requested_at' => now(),
            'reviewed_at' => null,
            'reviewer_id' => User::factory(),
            'requester_id' => User::factory(),
        ]);
    }

    /** A completed review (reviewed_at stamped). */
    public function reviewed(): static
    {
        return $this->state(fn () => [
            'review_requested_at' => now()->subDay(),
            'reviewed_at' => now(),
            'reviewer_id' => User::factory(),
        ]);
    }
}
