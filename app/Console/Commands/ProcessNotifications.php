<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessNotifications extends Command
{
    protected $signature = 'notifications:process';

    protected $description = 'Manually process queued jobs for at most one minute or 100 jobs';

    public function handle(): int
    {
        $this->info('Processing queued jobs within recovery limits...');

        // Never schedule this command: Supervisor must own queue consumption
        // so stopping its workers also stops automatic processing.
        return $this->call('queue:work', [
            '--stop-when-empty' => true,
            '--queue' => 'high,default,low',
            '--tries' => 3,
            '--backoff' => '10,30,60',
            '--timeout' => 60,
            '--max-time' => 60,
            '--max-jobs' => 100,
        ]);
    }
}
