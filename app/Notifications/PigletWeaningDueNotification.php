<?php

namespace App\Notifications;

use App\Models\PigManagement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Phase E3 hardening: typed pig parameter, null-safe field access, and a
 * graceful fail-soft path when the pig has been deleted between enqueue
 * and queue worker execution.
 *
 * SerializesModels means only the pig's id is serialised into the queue
 * payload — Laravel re-fetches the row at execute time. If the row is gone
 * by then, $this->pig will be null and toDatabase() returns a degraded
 * payload rather than throwing.
 */
class PigletWeaningDueNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public ?PigManagement $pig,
        public string $dueType,
        public int $daysUntilOrOverdue,
    ) {
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
        $earTag = $this->pig?->ear_tag_number ?? '(deleted)';
        $pigId  = $this->pig?->id;

        $message = match ($this->dueType) {
            'upcoming'  => "Piglet {$earTag} is due for weaning in {$this->daysUntilOrOverdue} days.",
            'due_today' => "Piglet {$earTag} is due for weaning today.",
            'overdue'   => "Weaning overdue for piglet {$earTag} by {$this->daysUntilOrOverdue} days.",
            default     => "Piglet {$earTag} has a pending weaning status.",
        };

        $color = match ($this->dueType) {
            'upcoming'  => 'yellow',
            'due_today' => 'orange',
            'overdue'   => 'red',
            default     => 'blue',
        };

        $icon = match ($this->dueType) {
            'upcoming'  => 'Pig',
            'due_today' => 'Pig',
            'overdue'   => 'Alert',
            default     => 'Warning',
        };

        return [
            'type'                  => 'piglet_weaning_' . $this->dueType,
            'pig_id'                => $pigId,
            'ear_tag'               => $earTag,
            'days_until_or_overdue' => $this->daysUntilOrOverdue,
            'link'                  => $pigId ? route('pigs.show', $pigId) : null,
            'message'               => $message,
            'color'                 => $color,
            'icon'                  => $icon,
            'title'                 => 'Piglet Weaning ' . ucwords(str_replace('_', ' ', $this->dueType)),
        ];
    }
}
