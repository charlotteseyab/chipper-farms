<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Weighing notifications: run continuously
        $schedule->command('weights:notify')->everyMinute();
        // Gestation notifications: run continuously to catch events in real-time
        $schedule->command('gestation:notify')->everyMinute();
                
        // Process notifications every 5 minutes
        $schedule->command('notifications:process')->everyFiveMinutes();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}


