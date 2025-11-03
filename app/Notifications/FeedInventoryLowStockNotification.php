<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class FeedInventoryLowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $inventory;
    protected $threshold;

    public function __construct($inventory, $threshold = 50)
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
            'type' => 'feed_low_stock',
            'title' => 'Feed Stock Running Low',
            'body' => "Feed stock for {$feedType->name} is running low. Current stock: {$currentStock}. Consider placing a purchase request.",
            'icon' => '⚠️',
            'color' => 'orange',
            'link' => route('feed-inventory.index'),
            'action_text' => 'View Inventory',
            'feed_name' => $feedType->name,
            'current_stock' => $currentStock,
        ]);
    }
}
