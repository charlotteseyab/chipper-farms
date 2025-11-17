<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class GestationOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public $mating)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
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


