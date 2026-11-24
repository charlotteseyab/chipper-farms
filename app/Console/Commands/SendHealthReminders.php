<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Health\HealthReminderService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Sends health reminders (decision D9) for every organisation. Safe to run
 * as often as the scheduler likes: each reminder is sent once.
 */
class SendHealthReminders extends Command
{
    protected $signature = 'health:notify {--organization= : Only this organisation id}';

    protected $description = 'Send in-app reminders for doses due or overdue, follow-ups, withdrawal ending, expiring batches and low medicine stock';

    public function handle(HealthReminderService $reminders, CurrentOrganization $current): int
    {
        $now = CarbonImmutable::now();
        $organizations = Organization::query()->when($this->option('organization'), fn ($q, $id) => $q->whereKey($id))->get();
        // Put back whatever organisation was bound before, so a caller (or a test) keeps its context.
        $previous = $current->get();

        foreach ($organizations as $organization) {
            // Health models are scoped to the bound organisation; bind each in
            // turn so one farm's reminders can never include another's records.
            $current->bind($organization);
            try {
                $sent = $reminders->send((int) $organization->id, $now);
            } finally {
                $current->bind($previous);
            }
            if ($sent !== []) {
                $this->info($organization->name.': '.collect($sent)->map(fn ($n, $type) => "{$n} {$type}")->implode(', '));
            }
        }

        return self::SUCCESS;
    }
}
