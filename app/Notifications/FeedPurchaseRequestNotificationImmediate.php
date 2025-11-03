<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class FeedPurchaseRequestNotificationImmediate extends Notification
{
    use Queueable;

    protected $request;
    protected $type;

    public function __construct($request, $type)
    {
        $this->request = $request;
        $this->type = $type; // 'new', 'approved', 'rejected'
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $message = $this->getMessage();
        $icon = $this->getIcon();
        $color = $this->getColor();

        return new DatabaseMessage([
            'type' => 'feed_purchase_request',
            'request_id' => $this->request->id,
            'title' => $this->getTitle(),
            'body' => $message,
            'icon' => $icon,
            'color' => $color,
            'link' => route('feed-purchase-requests.show', $this->request->id),
            'action_text' => 'View Request',
            'item_name' => $this->request->item_name ?: $this->request->feed_type,
            'quantity' => $this->request->quantity . ' ' . $this->request->unit,
            'cost' => 'GHC ' . number_format($this->request->cost_ghc, 2),
        ]);
    }

    private function getTitle()
    {
        return match($this->type) {
            'new' => 'New Feed Purchase Request',
            'approved' => 'Purchase Request Approved',
            'rejected' => 'Purchase Request Rejected',
            default => 'Feed Purchase Request Update'
        };
    }

    private function getMessage()
    {
        $requester = $this->request->requestedBy->name ?? 'Unknown User';
        $item = $this->request->item_name ?: $this->request->feed_type;
        $quantity = $this->request->quantity . ' ' . $this->request->unit;
        $cost = 'GHC ' . number_format($this->request->cost_ghc, 2);

        return match($this->type) {
            'new' => "{$requester} has submitted a new purchase request for {$item} ({$quantity}) - {$cost}",
            'approved' => "Your purchase request for {$item} ({$quantity}) has been approved. Total cost: {$cost}",
            'rejected' => "Your purchase request for {$item} ({$quantity}) has been rejected. Reason: " . ($this->request->decision_reason ?? 'No reason provided'),
            default => "Purchase request for {$item} has been updated"
        };
    }

    private function getIcon()
    {
        return match($this->type) {
            'new' => '🛒',
            'approved' => '✅',
            'rejected' => '❌',
            default => '📋'
        };
    }

    private function getColor()
    {
        return match($this->type) {
            'new' => 'blue',
            'approved' => 'green',
            'rejected' => 'red',
            default => 'gray'
        };
    }
}
