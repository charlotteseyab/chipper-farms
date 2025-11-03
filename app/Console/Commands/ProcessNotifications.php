<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
    use Illuminate\Support\Facades\Artisan;

class ProcessNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all queued notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing queued notifications...');
        
        // Check if there are any jobs in the queue
        $jobCount = \DB::table('jobs')->count();
        
        if ($jobCount > 0) {
            $this->info("Found {$jobCount} queued jobs. Processing...");
            
            // Process all queued jobs
            Artisan::call('queue:work', ['--stop-when-empty' => true]);
            
            $this->info('All notifications processed successfully!');
        } else {
            $this->info('No queued jobs found.');
        }
        
        return Command::SUCCESS;
    }
}