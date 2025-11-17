<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PigManagement;
use App\Models\User;
use App\Notifications\WeightOverdueNotification;
use App\Notifications\WeightDueSoonNotification;

class SendInitialOverdueNotifications extends Command
{
    protected $signature = 'weights:notify-initial {--force : Force send even if notification was sent today}';

    protected $description = 'Send initial notifications for all overdue and due soon pigs (useful after deployment)';

    public function handle(): int
    {
        $force = $this->option('force');
        $this->info('Sending initial weighing notifications for all overdue/due soon pigs...');

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

        $today = now()->toDateString();
        $dueSoonCount = 0;
        $overdueCount = 0;

        // Send due soon notifications
        foreach ($dueSoon as $pig) {
            foreach ($recipients as $user) {
                if ($force) {
                    // Force send regardless of existing notifications
                    $user->notify(new WeightDueSoonNotification($pig, $today));
                    $dueSoonCount++;
                } else {
                    // Check if notification already exists for today
                    $already = $user->notifications()
                        ->where('type', \App\Notifications\WeightDueSoonNotification::class)
                        ->where('data->pig_id', $pig->id)
                        ->where('data->date_key', $today)
                        ->exists();
                    if (!$already) {
                        $user->notify(new WeightDueSoonNotification($pig, $today));
                        $dueSoonCount++;
                    }
                }
            }
        }

        // Send overdue notifications
        foreach ($overdue as $pig) {
            foreach ($recipients as $user) {
                if ($force) {
                    // Force send regardless of existing notifications
                    $user->notify(new WeightOverdueNotification($pig, $today));
                    $overdueCount++;
                } else {
                    // Check if notification already exists for today
                    $already = $user->notifications()
                        ->where('type', \App\Notifications\WeightOverdueNotification::class)
                        ->where('data->pig_id', $pig->id)
                        ->where('data->date_key', $today)
                        ->exists();
                    if (!$already) {
                        $user->notify(new WeightOverdueNotification($pig, $today));
                        $overdueCount++;
                    }
                }
            }
        }

        $this->info("Notifications sent - Due soon: {$dueSoonCount}, Overdue: {$overdueCount}");
        $this->info("Total pigs processed - Due soon: {$dueSoon->count()}, Overdue: {$overdue->count()}");
        
        return self::SUCCESS;
    }
}

