<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Run daily at 01:00
        $schedule->command('weights:notify')->dailyAt('01:00');
        
        // Check feed inventory levels daily at 02:00
        $schedule->command('feeds:check-inventory')->dailyAt('02:00');
        
        // Process notifications every 5 minutes
        $schedule->command('notifications:process')->everyFiveMinutes();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}


