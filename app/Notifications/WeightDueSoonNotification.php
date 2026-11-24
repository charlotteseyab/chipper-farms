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
class WeightDueSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /** A notification that keeps failing stops after 3 tries instead of filling failed_jobs. */
    public $tries = 3;

    /** @var list<int> seconds before each retry */
    public $backoff = [10, 60, 300];

    public function __construct(public ?PigManagement $pig, public string $dateKey)
    {
        // Stored without loaded relations: the queue must rebuild whatever a
        // notification carries, and a relation it cannot rebuild fails the
        // job on every retry (the WeightOverdueNotification incident).
        $this->pig = $this->pig?->withoutRelations();
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
        $pigId = $this->pig?->id;

        return [
            'type'         => 'weight_due_soon',
            'pig_id'       => $pigId,
            'ear_tag'      => $this->pig?->ear_tag_number ?? '(deleted)',
            'days_since'   => $this->pig?->getDaysSinceLastWeighing(),
            'last_weighed' => optional($this->pig?->getLastWeighingDate())?->format('Y-m-d'),
            'link'         => $pigId ? route('weights.create', ['pig_id' => $pigId]) : null,
            'date_key'     => $this->dateKey,
            'message'      => 'Weighing due soon',
        ];
    }
}
