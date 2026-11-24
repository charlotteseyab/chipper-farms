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

        $this->recipients = [];
        $today = now()->startOfDay();

        $pregnant = Mating::with(['female'])
            ->where('pregnancy_status', 'pregnant')
            ->whereNotNull('expected_farrowing_date')
            ->lazyById(200);

        $dueSoonCount = 0; $dueTodayCount = 0; $overdueCount = 0;

        // One pregnancy with bad data must not stop reminders for every farm.
        $failures = [];
        foreach ($pregnant as $mating) {
            try {
                $this->notifyFor($mating, $dueSoonCount, $dueTodayCount, $overdueCount);
            } catch (\Throwable $e) {
                $failures[] = "mating {$mating->id}: " . $e->getMessage();
            }
        }

        if ($failures !== []) {
            \Illuminate\Support\Facades\Log::warning('gestation:notify skipped records', [
                'count' => count($failures),
                'first' => array_slice($failures, 0, 5),
            ]);
        }

        $this->info("Gestation: due soon={$dueSoonCount}, due today={$dueTodayCount}, overdue(7d)={$overdueCount}");
        return self::SUCCESS;
    }

    /** Send whichever milestone reminder this pregnancy is due today. */
    protected function notifyFor($mating, int &$dueSoonCount, int &$dueTodayCount, int &$overdueCount): void
    {
        $organizationId = (int) ($mating->organization_id ?: $mating->female?->organization_id);
        $recipients = $this->recipientsFor($organizationId);
        $expected = optional($mating->expected_farrowing_date)?->startOfDay();
        if (!$expected) { return; }
        $daysUntil = (int) now()->startOfDay()->diffInDays($expected, false); // positive=future, 0=today, negative=past

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
            return;
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
            return;
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

    /** @var array<int, \Illuminate\Support\Collection> recipients per farm, read once per run */
    protected array $recipients = [];

    protected function recipientsFor(int $organizationId)
    {
        return $this->recipients[$organizationId] ??= User::query()
            ->where('organization_id', $organizationId)
            ->where('active_status', true)
            ->with('roleRecord.permissions')
            ->get()
            ->filter(fn (User $user) => $user->isSuperuser() || $user->isAdmin() || $user->hasPermission('view_gestation'))
            // Drop the role.permissions loaded for the filter above. Queued
            // notifications store loaded relations and reload them, and `role`
            // is also a plain column on users, so reloading it fails.
            ->each->setRelations([]);
    }
}
