<?php

namespace App\Notifications\Channels;

use App\Services\Email\EmailTemplateService;
use App\Storefront\Support\StorefrontSettings;
use Illuminate\Notifications\Notification;

class TemplateMailChannel
{
    public function __construct(protected EmailTemplateService $emails)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toDatabase')) {
            return;
        }

        $organizationId = (int) ($notifiable->organization_id ?? 0);
        $recipient = $notifiable->routeNotificationFor('mail', $notification) ?? $notifiable->email ?? null;
        if ($organizationId < 1 || ! is_string($recipient)) {
            return;
        }

        $data = $notification->toDatabase($notifiable);
        $type = (string) ($data['type'] ?? '');
        $templateKey = match (true) {
            str_starts_with($type, 'gestation_') => 'operations.gestation_due',
            str_starts_with($type, 'piglet_weaning_') => 'operations.weaning_due',
            str_starts_with($type, 'weight_') => 'operations.weight_due',
            $type === 'storefront_quote_requested' => 'storefront.quote_staff_alert',
            default => null,
        };

        if ($templateKey === null) {
            return;
        }

        $this->emails->send($organizationId, $templateKey, $recipient, [
            'pig_tag' => $data['ear_tag'] ?? 'Animal',
            'due_date' => $data['expected_farrowing_date'] ?? $data['date_key'] ?? 'See system',
            'quote_reference' => $data['reference'] ?? '',
            'customer_name' => $data['contact_name'] ?? '',
            'action_url' => $data['link'] ?? '',
            'store_name' => StorefrontSettings::storeName($organizationId),
            'support_email' => StorefrontSettings::supportEmail($organizationId),
            'support_phone' => StorefrontSettings::supportPhone($organizationId),
        ]);
    }
}
