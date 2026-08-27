<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SystemNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $key,
        public string $module,
        public string $title,
        public string $message,
        public ?string $link = null,
        public string $severity = 'info',
        public array $context = [],
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => $this->key,
            'module' => $this->module,
            'title' => $this->title,
            'message' => $this->message,
            'link' => $this->link,
            'severity' => $this->severity,
            'context' => $this->context,
        ];
    }
}
