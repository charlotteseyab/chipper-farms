<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class GestationDueTodayNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public $mating)
    {
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
        return [
            'type' => 'gestation_due_today',
            'sow_id' => $sow?->id,
            'ear_tag' => $sow?->ear_tag_number,
            'expected_farrowing_date' => optional($this->mating->expected_farrowing_date)?->format('Y-m-d'),
            'days_until_due' => 0,
            'link' => route('gestation.index', ['filter_date' => optional($this->mating->expected_farrowing_date)?->format('Y-m-d')]),
            'message' => 'Gestation due today',
        ];
    }
}

