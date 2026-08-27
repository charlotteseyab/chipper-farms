<?php

namespace App\Notifications;

use App\Models\Storefront\StorefrontQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifies sales staff that a new storefront quote request has arrived.
 * Database channel only — surfaces in the existing notifications panel.
 */
class StorefrontQuoteRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public StorefrontQuote $quote)
    {
    }

    public function via($notifiable): array
    {
        return ['database', \App\Notifications\Channels\TemplateMailChannel::class];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type'         => 'storefront_quote_requested',
            'quote_id'     => $this->quote->id,
            'reference'    => $this->quote->reference,
            'contact_name' => $this->quote->contact_name,
            'item_count'   => $this->quote->items()->count(),
            'link'         => route('storefront.admin.quotes.show', $this->quote),
            'message'      => "New quote request {$this->quote->reference} from {$this->quote->contact_name}",
        ];
    }
}
