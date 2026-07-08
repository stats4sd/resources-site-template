<?php

namespace App\Console;

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
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
