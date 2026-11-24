<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PigManagement;
use App\Models\User;
use App\Notifications\WeightDueSoonNotification;
use App\Notifications\WeightOverdueNotification;

class GenerateWeighingNotifications extends Command
{
    protected $signature = 'weights:notify {--force : Send even if the pig was already notified today}';

    /** @var array<int, \Illuminate\Support\Collection> recipients per farm, read once per run */
    protected array $recipients = [];

    protected $description = 'Generate in-app notifications for pigs due soon or overdue for weighing';

    public function handle(): int
    {
        $this->info('Generating weighing notifications...');

        $this->recipients = [];
        $dueSoon = 0;
        $overdue = 0;
        $failureCount = 0;
        $failures = [];
        $today = now()->toDateString();
        $sent = $this->option('force') ? [] : $this->sentToday($today);
        $recordFailure = static function (string $message) use (&$failureCount, &$failures): void {
            $failureCount++;
            if (count($failures) < 5) {
                $failures[] = mb_substr($message, 0, 2000);
            }
        };

        // Keep only one page of pigs in memory, even on a large farm.
        foreach (PigManagement::active()->lazyById(200) as $pig) {
            try {
                $status = $pig->getWeighingStatus();
            } catch (\Throwable $e) {
                $recordFailure("pig {$pig->id}: " . $e->getMessage());
                continue;
            }

            if ($status['status'] === 'due_soon') {
                $type = WeightDueSoonNotification::class;
                $dueSoon++;
            } elseif (in_array($status['status'], ['overdue', 'critical'], true)) {
                $type = WeightOverdueNotification::class;
                $overdue++;
            } else {
                continue;
            }

            foreach ($this->recipientsFor((int) $pig->organization_id) as $user) {
                $key = "{$type}|{$user->id}|{$pig->id}";
                if (isset($sent[$key])) {
                    continue;
                }
                try {
                    $user->withoutRelations()->notify(new $type($pig->withoutRelations(), $today));
                    $sent[$key] = true;
                } catch (\Throwable $e) {
                    $recordFailure("pig {$pig->id} to user {$user->id}: " . $e->getMessage());
                }
            }
        }

        if ($failures !== []) {
            \Illuminate\Support\Facades\Log::warning('weights:notify skipped records', [
                'count' => $failureCount,
                'first' => array_slice($failures, 0, 5),
            ]);
        }

        $this->info("Due soon: {$dueSoon}, Overdue: {$overdue}" . ($failureCount ? ', skipped: ' . $failureCount : ''));
        return $failureCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<string, true> "type|user id|pig id" for reminders already sent today */
    protected function sentToday(string $today): array
    {
        $sent = [];
        \Illuminate\Support\Facades\DB::table('notifications')
            ->whereIn('type', [WeightDueSoonNotification::class, WeightOverdueNotification::class])
            ->where('created_at', '>=', now()->startOfDay())
            ->orderBy('id')
            ->select(['type', 'notifiable_id', 'data'])
            ->lazy()
            ->each(function ($row) use (&$sent, $today) {
                $data = json_decode((string) $row->data, true) ?: [];
                if (($data['date_key'] ?? null) === $today && isset($data['pig_id'])) {
                    $sent["{$row->type}|{$row->notifiable_id}|{$data['pig_id']}"] = true;
                }
            });

        return $sent;
    }

    protected function recipientsFor(int $organizationId)
    {
        return $this->recipients[$organizationId] ??= User::query()
            ->where('organization_id', $organizationId)
            ->where('active_status', true)
            ->with('roleRecord.permissions')
            ->get()
            ->filter(fn (User $user) => $user->isSuperuser() || $user->isAdmin() || $user->hasPermission('view_weights'))
            // Drop the role.permissions loaded for the filter above. Queued
            // notifications store loaded relations and reload them, and `role`
            // is also a plain column on users, so reloading it fails.
            ->each->setRelations([]);
    }
}
