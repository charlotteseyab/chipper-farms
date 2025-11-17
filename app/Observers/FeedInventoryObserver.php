<?php

namespace App\Observers;

use App\Models\FeedInventory;
use App\Models\User;
use App\Notifications\FeedInventoryCriticalStockNotification;
use Illuminate\Support\Facades\Notification;

class FeedInventoryObserver
{
    public function updated(FeedInventory $inventory)
    {
        $this->checkStockLevels($inventory);
    }

    public function created(FeedInventory $inventory)
    {
        $this->checkStockLevels($inventory);
    }

    private function checkStockLevels(FeedInventory $inventory): void
    {
        $quantity = (float) $inventory->quantity;
        
        if ($quantity <= 0) {
            return;
        }

        $unit = strtolower($inventory->unit ?? '');
        $threshold = str_contains($unit, 'bag') ? 5 : 50; // bags vs kg

        if ($quantity < $threshold) {
            $this->sendCriticalStockNotification($inventory, $threshold);
        }
    }

    private function sendCriticalStockNotification(FeedInventory $inventory, float $threshold): void
    {
        $users = User::whereHas('role', function($query) {
            $query->whereIn('name', ['admin', 'superuser', 'farmmanager']);
        })->get();
        
        Notification::send($users, new FeedInventoryCriticalStockNotification($inventory, $threshold));
    }
}
