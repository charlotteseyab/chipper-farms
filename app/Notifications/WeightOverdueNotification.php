<?php

namespace App\Notifications;

use App\Models\PigManagement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Phase E3 hardening: typed pig + null safety + SerializesModels.
 */
class WeightOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public ?PigManagement $pig, public string $dateKey)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $pigId = $this->pig?->id;

        return [
            'type'         => 'weight_overdue',
            'pig_id'       => $pigId,
            'ear_tag'      => $this->pig?->ear_tag_number ?? '(deleted)',
            'days_since'   => $this->pig?->getDaysSinceLastWeighing(),
            'last_weighed' => optional($this->pig?->getLastWeighingDate())?->format('Y-m-d'),
            'link'         => $pigId ? route('weights.create', ['pig_id' => $pigId]) : null,
            'date_key'     => $this->dateKey,
            'message'      => 'Weighing overdue',
        ];
    }
}
