<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /** The current password being used by the factory (hashed with the test config). */
    protected static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->withRole(UserRole::Admin);
    }

    public function editor(): static
    {
        return $this->withRole(UserRole::Editor);
    }

    public function viewer(): static
    {
        return $this->withRole(UserRole::Viewer);
    }

    /**
     * Assign a spatie role after creation, lazily creating the role so factory states work
     * even when the role seeder hasn't run (the roles also exist via the data migration).
     */
    protected function withRole(UserRole $role): static
    {
        return $this->afterCreating(function ($user) use ($role): void {
            Role::findOrCreate($role->value, 'web');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $user->assignRole($role->value);
        });
    }
}
