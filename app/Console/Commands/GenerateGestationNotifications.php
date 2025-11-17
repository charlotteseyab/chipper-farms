<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Mating;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use App\Notifications\GestationDueSoonNotification;
use App\Notifications\GestationDueTodayNotification;
use App\Notifications\GestationOverdueNotification;

class GenerateGestationNotifications extends Command
{
    protected $signature = 'gestation:notify';

    protected $description = 'Generate notifications for gestation due soon, due today, and 1 week overdue';

    public function handle(): int
    {
        $this->info('Generating gestation notifications...');

        $today = now()->startOfDay();

        $pregnant = Mating::with(['female'])
            ->where('pregnancy_status', 'pregnant')
            ->whereNotNull('expected_farrowing_date')
            ->get();

        // Recipients: dataentry, farmmanager, admin, superuser
        $recipients = User::whereHas('role', function($q) {
            $q->whereIn('name', ['dataentry','farmmanager','admin','superuser']);
        })->get();

        $dueSoonCount = 0; $dueTodayCount = 0; $overdueCount = 0;

        foreach ($pregnant as $mating) {
            $expected = optional($mating->expected_farrowing_date)?->startOfDay();
            if (!$expected) { continue; }
            $daysUntil = now()->startOfDay()->diffInDays($expected, false); // positive=future, 0=today, negative=past

            // 3 days to due
            if ($daysUntil === 3) {
                foreach ($recipients as $user) {
                    $already = $user->notifications()
                        ->where('type', \App\Notifications\GestationDueSoonNotification::class)
                        ->where('data->sow_id', $mating->female_id)
                        ->where('data->expected_farrowing_date', $expected->toDateString())
                        ->whereDate('created_at', now()->toDateString())
                        ->exists();
                    if (!$already) {
                        $user->notify(new GestationDueSoonNotification($mating));
                        $dueSoonCount++;
                    }
                }
                continue;
            }
            // Due today
            if ($daysUntil === 0) {
                foreach ($recipients as $user) {
                    $already = $user->notifications()
                        ->where('type', \App\Notifications\GestationDueTodayNotification::class)
                        ->where('data->sow_id', $mating->female_id)
                        ->where('data->expected_farrowing_date', $expected->toDateString())
                        ->whereDate('created_at', now()->toDateString())
                        ->exists();
                    if (!$already) {
                        $user->notify(new GestationDueTodayNotification($mating));
                        $dueTodayCount++;
                    }
                }
                continue;
            }
            // 1 week overdue (7 days past expected) and still marked pregnant
            if ($daysUntil === -7) {
                foreach ($recipients as $user) {
                    $already = $user->notifications()
                        ->where('type', \App\Notifications\GestationOverdueNotification::class)
                        ->where('data->sow_id', $mating->female_id)
                        ->where('data->expected_farrowing_date', $expected->toDateString())
                        ->whereDate('created_at', now()->toDateString())
                        ->exists();
                    if (!$already) {
                        $user->notify(new GestationOverdueNotification($mating));
                        $overdueCount++;
                    }
                }
            }
        }

        $this->info("Gestation: due soon={$dueSoonCount}, due today={$dueTodayCount}, overdue(7d)={$overdueCount}");
        return self::SUCCESS;
    }
}


