<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SendInitialOverdueNotifications extends Command
{
    protected $signature = 'weights:notify-initial {--force : Force send even if notification was sent today}';

    protected $description = 'Send initial notifications for all overdue and due soon pigs (useful after deployment)';

    /**
     * Runs weights:notify, which works farm by farm. This command used to
     * send every farm's overdue pigs to every farm's staff.
     */
    public function handle(): int
    {
        return $this->call('weights:notify', $this->option('force') ? ['--force' => true] : []);
    }
}
