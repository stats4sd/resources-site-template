<?php

namespace App\Console;

use App\Models\Collection;
use App\Models\Trove;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Laravel\Telescope\Telescope;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        if ($this->app->environment('local')) {
            if (class_exists(Telescope::class)) {
                $schedule->command('app:purge-telescope-entries')->weeklyOn(dayOfWeek: Schedule::TUESDAY);
            }
        }

        $schedule->command('app:prune-superseded-media')->daily();

        // Closes the silent-drift gap if the engine was unreachable at publish/reindex time.
        $schedule->command('scout:import', [Trove::class])
            ->daily()
            ->when(fn () => config('scout.driver') === 'meilisearch');

        $schedule->command('scout:import', [Collection::class])
            ->daily()
            ->when(fn () => config('scout.driver') === 'meilisearch');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
