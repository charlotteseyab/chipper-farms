<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class FeedInventoryCriticalStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $inventory;
    protected $threshold;

    public function __construct($inventory, $threshold = 10)
    {
        $this->inventory = $inventory;
        $this->threshold = $threshold;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $feedType = $this->inventory->feedType;
        $currentStock = $this->inventory->quantity . ' ' . $this->inventory->unit;
        
        return new DatabaseMessage([
            'type' => 'feed_critical_stock',
            'title' => 'Critical Feed Stock Alert',
            'body' => "URGENT: Feed stock for {$feedType->name} is critically low at {$currentStock}. Immediate purchase required!",
            'icon' => '🚨',
            'color' => 'red',
            'link' => route('feed-purchase-requests.create'),
            'action_text' => 'Create Purchase Request',
            'feed_name' => $feedType->name,
            'current_stock' => $currentStock,
        ]);
    }
}
