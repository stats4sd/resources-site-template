<?php

namespace Database\Factories;

use App\Models\PasswordSetup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PasswordSetup>
 */
class PasswordSetupFactory extends Factory
{
    protected $model = PasswordSetup::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // token + expires_at are set by PasswordSetup::creating() when left null.
            'expires_at' => now()->addDays(PasswordSetup::EXPIRY_DAYS),
            'used_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'used_at' => now(),
        ]);
    }
}
