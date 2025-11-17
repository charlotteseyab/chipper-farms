<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FeedInventory;
use App\Models\User;
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
        $criticalStockCount = 0;
        
        // Get users to notify
        $users = User::whereHas('role', function($query) {
            $query->whereIn('name', ['admin', 'superuser', 'farmmanager']);
        })->get();
        
        foreach ($inventories as $inventory) {
            $quantity = (float) $inventory->quantity;
            if ($quantity <= 0) {
                continue;
            }

            $unit = strtolower($inventory->unit ?? '');
            $threshold = str_contains($unit, 'bag') ? 5 : 50;

            if ($quantity < $threshold) {
                $this->warn("Critical stock: {$inventory->feedType->name} - {$quantity} {$inventory->unit} (threshold {$threshold} " . (str_contains($unit, 'bag') ? 'bags' : 'kg') . ")");
                Notification::send($users, new FeedInventoryCriticalStockNotification($inventory, $threshold));
                $criticalStockCount++;
            }
        }
        
        $this->info("Inventory check complete. Critical stock items: {$criticalStockCount}");
        
        return Command::SUCCESS;
    }
}
