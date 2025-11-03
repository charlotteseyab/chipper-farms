<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FeedInventory;
use App\Models\User;
use App\Notifications\FeedInventoryLowStockNotification;
use App\Notifications\FeedInventoryCriticalStockNotification;
use Illuminate\Support\Facades\Notification;

class CheckFeedInventoryLevels extends Command
{
    protected $signature = 'feeds:check-inventory';
    protected $description = 'Check feed inventory levels and send notifications for low stock';

    public function handle()
    {
        $this->info('Checking feed inventory levels...');
        
        $inventories = FeedInventory::with('feedType')->get();
        $lowStockCount = 0;
        $criticalStockCount = 0;
        
        // Get users to notify
        $users = User::whereHas('role', function($query) {
            $query->whereIn('name', ['admin', 'superuser', 'farmmanager']);
        })->get();
        
        foreach ($inventories as $inventory) {
            $quantity = $inventory->quantity;
            
            // Critical stock alert (≤ 10kg or equivalent)
            if ($quantity <= 10) {
                $this->warn("Critical stock: {$inventory->feedType->name} - {$quantity} {$inventory->unit}");
                Notification::send($users, new FeedInventoryCriticalStockNotification($inventory, 10));
                $criticalStockCount++;
            }
            // Low stock alert (≤ 50kg or equivalent)
            elseif ($quantity <= 50) {
                $this->info("Low stock: {$inventory->feedType->name} - {$quantity} {$inventory->unit}");
                Notification::send($users, new FeedInventoryLowStockNotification($inventory, 50));
                $lowStockCount++;
            }
        }
        
        $this->info("Inventory check complete. Low stock: {$lowStockCount}, Critical stock: {$criticalStockCount}");
        
        return Command::SUCCESS;
    }
}
