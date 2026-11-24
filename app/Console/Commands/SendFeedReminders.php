<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Feed\FeedReminderService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Sends feed reminders for every organisation: feed running low, feedings not
 * recorded, ingredients low or short, plans waiting, large variances. Safe to
 * run as often as the scheduler likes: each reminder is sent once.
 */
class SendFeedReminders extends Command
{
    protected $signature = 'feed:notify {--organization= : Only this organisation id}';

    protected $description = 'Send in-app feed reminders: stock running low, feedings not recorded, ingredients low or short, plans waiting';

    public function handle(FeedReminderService $reminders, CurrentOrganization $current): int
    {
        $now = CarbonImmutable::now();
        $organizations = Organization::query()->when($this->option('organization'), fn ($q, $id) => $q->whereKey($id))->get();
        // Put back whatever organisation was bound before, so a caller (or a test) keeps its context.
        $previous = $current->get();

        foreach ($organizations as $organization) {
            // Feed models are scoped to the bound organisation; bind each in turn.
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
