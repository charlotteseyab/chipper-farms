<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class WeightOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public $pig, public string $dateKey)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'weight_overdue',
            'pig_id' => $this->pig->id,
            'ear_tag' => $this->pig->ear_tag_number,
            'days_since' => $this->pig->getDaysSinceLastWeighing(),
            'last_weighed' => optional($this->pig->getLastWeighingDate())?->format('Y-m-d'),
            'link' => route('weights.create', ['pig_id' => $this->pig->id]),
            'date_key' => $this->dateKey,
            'message' => 'Weighing overdue',
        ];
    }
}


