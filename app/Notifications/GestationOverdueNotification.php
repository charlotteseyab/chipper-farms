<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class GestationOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** A notification that keeps failing stops after 3 tries instead of filling failed_jobs. */
    public $tries = 3;

    /** @var list<int> seconds before each retry */
    public $backoff = [10, 60, 300];

    public function __construct(public $mating)
    {
        // Stored without loaded relations: the queue must rebuild whatever a
        // notification carries, and a relation it cannot rebuild fails the
        // job on every retry (the WeightOverdueNotification incident).
        $this->mating = $this->mating?->withoutRelations();
    }

    public function via($notifiable): array
    {
        return ['database', \App\Notifications\Channels\TemplateMailChannel::class];
    }

    /**
     * The in-app notification is written straight away; only the email waits
     * for the queue. Queued in-app rows appeared only when a worker ran, and
     * the scheduler's "already sent today?" check could not see them yet.
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    public function toDatabase($notifiable): array
    {
        $sow = $this->mating->female;
        $daysOverdue = optional($this->mating->expected_farrowing_date)?->diffInDays(now(), false);
        return [
            'type' => 'gestation_overdue',
            'sow_id' => $sow?->id,
            'ear_tag' => $sow?->ear_tag_number,
            'expected_farrowing_date' => optional($this->mating->expected_farrowing_date)?->format('Y-m-d'),
            'days_overdue' => $daysOverdue,
            'link' => route('gestation.index', ['filter_date' => optional($this->mating->expected_farrowing_date)?->format('Y-m-d')]),
            'message' => 'Gestation overdue by 1 week',
        ];
    }
}

