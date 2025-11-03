<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class FeedPurchaseRequestNotification extends Notification implements ShouldQueue
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
        switch ($this->type) {
            case 'new':
                return 'New Feed Purchase Request';
            case 'approved':
                return 'Purchase Request Approved';
            case 'rejected':
                return 'Purchase Request Rejected';
            default:
                return 'Feed Purchase Request Update';
        }
    }

    private function getMessage()
    {
        $itemName = $this->request->item_name ?: $this->request->feed_type;
        $quantity = $this->request->quantity . ' ' . $this->request->unit;
        $cost = 'GHC ' . number_format($this->request->cost_ghc, 2);

        switch ($this->type) {
            case 'new':
                return "New purchase request for {$itemName} ({$quantity}) - {$cost} by {$this->request->requestedBy->name}";
            case 'approved':
                return "Your request for {$itemName} ({$quantity}) has been approved by {$this->request->decider->name}";
            case 'rejected':
                return "Your request for {$itemName} ({$quantity}) has been rejected. Reason: " . ($this->request->decision_reason ?? 'No reason provided');
            default:
                return "Purchase request for {$itemName} has been updated";
        }
    }

    private function getIcon()
    {
        switch ($this->type) {
            case 'new':
                return '🛒';
            case 'approved':
                return '✅';
            case 'rejected':
                return '❌';
            default:
                return '📋';
        }
    }

    private function getColor()
    {
        switch ($this->type) {
            case 'new':
                return 'blue';
            case 'approved':
                return 'green';
            case 'rejected':
                return 'red';
            default:
                return 'gray';
        }
    }
}
