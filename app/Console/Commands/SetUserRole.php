<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Bootstrap the first admin on a fresh deploy, or recover from a lockout. Assigns exactly
 * one role (via syncRoles), and refuses to demote the last admin without --force.
 *
 *   php artisan user:set-role admin@example.com admin
 *   php artisan user:set-role someone@example.com viewer --force
 */
class SetUserRole extends Command
{
    protected $signature = 'user:set-role {email : The user\'s email address} {role : One of: viewer, editor, admin} {--force : Allow demoting the last admin}';

    protected $description = 'Assign a role (viewer/editor/admin) to a user by email.';

    public function handle(): int
    {
        $email = $this->argument('email');
        $roleValue = $this->argument('role');

        $role = UserRole::tryFrom($roleValue);
        if (! $role) {
            $this->error("Invalid role \"{$roleValue}\". Valid roles: ".implode(', ', array_column(UserRole::cases(), 'value')).'.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("No user found with email \"{$email}\".");

            return self::FAILURE;
        }

        if ($role !== UserRole::Admin && $user->isLastAdmin() && ! $this->option('force')) {
            $this->error("\"{$email}\" is the last admin; demoting them would leave the site without an administrator. Re-run with --force to override.");

            return self::FAILURE;
        }

        $user->syncRoles([$role->value]);

        $this->info("Set role \"{$role->value}\" for {$email}.");

        return self::SUCCESS;
    }
}
