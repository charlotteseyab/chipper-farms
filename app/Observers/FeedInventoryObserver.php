<?php

namespace App\Observers;

use App\Models\FeedInventory;
use App\Models\User;
use App\Notifications\FeedInventoryLowStockNotification;
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

    private function checkStockLevels(FeedInventory $inventory)
    {
        $quantity = $inventory->quantity;
        
        // Critical stock alert (≤ 10kg or equivalent)
        if ($quantity <= 10) {
            $this->sendCriticalStockNotification($inventory);
        }
        // Low stock alert (≤ 50kg or equivalent)
        elseif ($quantity <= 50) {
            $this->sendLowStockNotification($inventory);
        }
    }

    private function sendCriticalStockNotification(FeedInventory $inventory)
    {
        // Get all admins and managers
        $users = User::whereHas('role', function($query) {
            $query->whereIn('name', ['admin', 'superuser', 'farmmanager']);
        })->get();
        
        Notification::send($users, new FeedInventoryCriticalStockNotification($inventory, 10));
    }

    private function sendLowStockNotification(FeedInventory $inventory)
    {
        // Get all admins and managers
        $users = User::whereHas('role', function($query) {
            $query->whereIn('name', ['admin', 'superuser', 'farmmanager']);
        })->get();
        
        Notification::send($users, new FeedInventoryLowStockNotification($inventory, 50));
    }
}
