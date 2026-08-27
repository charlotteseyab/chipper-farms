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

