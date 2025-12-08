<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class PigletWeaningDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $pig; // PigManagement instance
    public $dueType; // 'upcoming', 'due_today', 'overdue'
    public $daysUntilOrOverdue;

    public function __construct($pig, string $dueType, int $daysUntilOrOverdue)
    {
        $this->pig = $pig;
        $this->dueType = $dueType;
        $this->daysUntilOrOverdue = $daysUntilOrOverdue;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $message = match($this->dueType) {
            'upcoming'  => "Piglet {$this->pig->ear_tag_number} is due for weaning in {$this->daysUntilOrOverdue} days.",
            'due_today' => "Piglet {$this->pig->ear_tag_number} is due for weaning today.",
            'overdue'   => "Weaning overdue for piglet {$this->pig->ear_tag_number} by {$this->daysUntilOrOverdue} days.",
            default     => "Piglet {$this->pig->ear_tag_number} has a pending weaning status."
        };

        $color = match($this->dueType) {
            'upcoming'  => 'yellow',
            'due_today' => 'orange',
            'overdue'   => 'red',
            default     => 'blue',
        };

        $icon = match($this->dueType) {
            'upcoming'  => '🐖',
            'due_today' => '🐖',
            'overdue'   => '🚨',
            default     => '⚠️',
        };

        return [
            'type'      => 'piglet_weaning_'.$this->dueType,
            'pig_id'    => $this->pig->id,
            'ear_tag'   => $this->pig->ear_tag_number,
            'days_until_or_overdue' => $this->daysUntilOrOverdue,
            'link'      => route('pigs.show', $this->pig->id),
            'message'   => $message,
            'color'     => $color,
            'icon'      => $icon,
            'title'     => 'Piglet Weaning '.ucwords(str_replace('_',' ',$this->dueType)),
        ];
    }
}
