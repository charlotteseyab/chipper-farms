<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PigManagement;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use App\Notifications\WeightDueSoonNotification;
use App\Notifications\WeightOverdueNotification;

class GenerateWeighingNotifications extends Command
{
    protected $signature = 'weights:notify';

    protected $description = 'Generate in-app notifications for pigs due soon or overdue for weighing';

    public function handle(): int
    {
        $this->info('Generating weighing notifications...');

        $pigs = PigManagement::active()->get();
        $dueSoon = collect();
        $overdue = collect();

        foreach ($pigs as $pig) {
            $status = $pig->getWeighingStatus();
            if ($status['status'] === 'due_soon') {
                $dueSoon->push($pig);
            } elseif (in_array($status['status'], ['overdue','critical'])) {
                $overdue->push($pig);
            }
        }

        // Target recipients: dataentry, farmmanager, admin, superuser
        $recipients = User::whereHas('role', function($q) {
            $q->whereIn('name', ['dataentry','farmmanager','admin','superuser']);
        })->get();

        // Avoid duplicate spam: naive approach - send one notification per pig per day
        $today = now()->toDateString();

        foreach ($dueSoon as $pig) {
            Notification::send($recipients, new WeightDueSoonNotification($pig, $today));
        }
        foreach ($overdue as $pig) {
            Notification::send($recipients, new WeightOverdueNotification($pig, $today));
        }

        $this->info("Due soon: {$dueSoon->count()}, Overdue: {$overdue->count()}");
        return self::SUCCESS;
    }
}


