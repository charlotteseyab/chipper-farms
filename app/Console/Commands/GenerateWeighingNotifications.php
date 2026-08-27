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

        // Avoid duplicate spam: naive approach - send one notification per pig per day
        $today = now()->toDateString();

        foreach ($dueSoon as $pig) {
            $recipients = $this->recipientsFor((int) $pig->organization_id);
            foreach ($recipients as $user) {
                $already = $user->notifications()
                    ->where('type', \App\Notifications\WeightDueSoonNotification::class)
                    ->where('data->pig_id', $pig->id)
                    ->where('data->date_key', $today)
                    ->exists();
                if (!$already) {
                    $user->notify(new WeightDueSoonNotification($pig, $today));
                }
            }
        }
        foreach ($overdue as $pig) {
            $recipients = $this->recipientsFor((int) $pig->organization_id);
            foreach ($recipients as $user) {
                $already = $user->notifications()
                    ->where('type', \App\Notifications\WeightOverdueNotification::class)
                    ->where('data->pig_id', $pig->id)
                    ->where('data->date_key', $today)
                    ->exists();
                if (!$already) {
                    $user->notify(new WeightOverdueNotification($pig, $today));
                }
            }
        }

        $this->info("Due soon: {$dueSoon->count()}, Overdue: {$overdue->count()}");
        return self::SUCCESS;
    }

    protected function recipientsFor(int $organizationId)
    {
        return User::query()
            ->where('organization_id', $organizationId)
            ->where('active_status', true)
            ->with('role.permissions')
            ->get()
            ->filter(fn (User $user) => $user->isSuperuser() || $user->isAdmin() || $user->hasPermission('view_weights'));
    }
}
