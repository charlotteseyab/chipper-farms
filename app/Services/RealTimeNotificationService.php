<?php

namespace App\Services;

use App\Models\PigManagement;
use App\Models\Mating;
use App\Models\FeedInventory;
use App\Models\IngredientInventory;
use Carbon\Carbon;

class RealTimeNotificationService
{
    /**
     * Get all real-time alerts for the system
     * 
     * @return array
     */
    public static function getAllAlerts(): array
    {
        return [
            'weight' => self::getWeightAlerts(),
            'gestation' => self::getGestationAlerts(),
            'feed' => self::getFeedAlerts(),
        ];
    }

    /**
     * Get weight management alerts (overdue, due soon)
     * 
     * @return array
     */
    public static function getWeightAlerts(): array
    {
        $weighingIntervalDays = 30; // Default weighing interval
        
        // Get all active pigs and filter by weighing status
        $pigs = PigManagement::active()->get();
        
        $overduePigs = collect();
        $dueSoonPigs = collect();
        $criticalPigs = collect();
        
        foreach ($pigs as $pig) {
            $status = $pig->getWeighingStatus();
            
            if ($status['status'] === 'critical') {
                $criticalPigs->push($pig);
            } elseif ($status['status'] === 'overdue') {
                $overduePigs->push($pig);
            } elseif ($status['status'] === 'due_soon') {
                $dueSoonPigs->push($pig);
            }
        }
        
        $alerts = [];
        
        // Critical alerts (most urgent)
        if ($criticalPigs->count() > 0) {
            $alerts[] = [
                'type' => 'weight_critical',
                'title' => 'Pigs Critically Overdue for Weighing',
                'message' => "{$criticalPigs->count()} pig(s) are critically overdue for weighing",
                'count' => $criticalPigs->count(),
                'severity' => 'high',
                'color' => 'red',
                'icon' => '🚨',
                'link' => route('weights.index') . '?overdue=1',
                'items' => $criticalPigs->take(5)->map(function($pig) {
                    return [
                        'id' => $pig->id,
                        'ear_tag' => $pig->ear_tag_number,
                        'code' => $pig->code,
                        'days_since' => $pig->getDaysSinceLastWeighing(),
                        'link' => route('pigs.show', $pig->id),
                    ];
                })->toArray(),
            ];
        }
        
        // Overdue alerts
        if ($overduePigs->count() > 0) {
            $alerts[] = [
                'type' => 'weight_overdue',
                'title' => 'Pigs Overdue for Weighing',
                'message' => "{$overduePigs->count()} pig(s) are overdue for weighing",
                'count' => $overduePigs->count(),
                'severity' => 'high',
                'color' => 'orange',
                'icon' => '⚖️',
                'link' => route('weights.index') . '?overdue=1',
                'items' => $overduePigs->take(5)->map(function($pig) {
                    return [
                        'id' => $pig->id,
                        'ear_tag' => $pig->ear_tag_number,
                        'code' => $pig->code,
                        'days_since' => $pig->getDaysSinceLastWeighing(),
                        'link' => route('pigs.show', $pig->id),
                    ];
                })->toArray(),
            ];
        }
        
        // Due soon alerts
        if ($dueSoonPigs->count() > 0) {
            $alerts[] = [
                'type' => 'weight_due_soon',
                'title' => 'Pigs Due Soon for Weighing',
                'message' => "{$dueSoonPigs->count()} pig(s) are due soon for weighing",
                'count' => $dueSoonPigs->count(),
                'severity' => 'medium',
                'color' => 'yellow',
                'icon' => '⚠️',
                'link' => route('weights.index') . '?due_soon=1',
                'items' => $dueSoonPigs->take(5)->map(function($pig) {
                    return [
                        'id' => $pig->id,
                        'ear_tag' => $pig->ear_tag_number,
                        'code' => $pig->code,
                        'days_since' => $pig->getDaysSinceLastWeighing(),
                        'link' => route('pigs.show', $pig->id),
                    ];
                })->toArray(),
            ];
        }
        
        return [
            'alerts' => $alerts,
            'total_count' => $overduePigs->count() + $dueSoonPigs->count() + $criticalPigs->count(),
            'overdue_count' => $overduePigs->count(),
            'due_soon_count' => $dueSoonPigs->count(),
            'critical_count' => $criticalPigs->count(),
        ];
    }

    /**
     * Get gestation alerts (due soon, due today, overdue)
     * 
     * @return array
     */
    public static function getGestationAlerts(): array
    {
        $today = now()->startOfDay();
        
        $pregnant = Mating::with(['female'])
            ->where('pregnancy_status', 'pregnant')
            ->whereNotNull('expected_farrowing_date')
            ->get();
        
        $dueSoon = collect();
        $dueToday = collect();
        $overdue = collect();
        
        foreach ($pregnant as $mating) {
            $expected = optional($mating->expected_farrowing_date)?->startOfDay();
            if (!$expected) {
                continue;
            }
            
            $daysUntil = $today->diffInDays($expected, false); // positive=future, 0=today, negative=past
            
            // Overdue (past expected date) - check first as highest priority
            if ($daysUntil < 0) {
                $overdue->push($mating);
            }
            // Due today
            elseif ($daysUntil === 0) {
                $dueToday->push($mating);
            }
            // Due in 3 days
            elseif ($daysUntil === 3) {
                $dueSoon->push($mating);
            }
        }
        
        $alerts = [];
        
        // Overdue alerts
        if ($overdue->count() > 0) {
            $alerts[] = [
                'type' => 'gestation_overdue',
                'title' => 'Gestation Overdue',
                'message' => "{$overdue->count()} sow(s) are overdue for farrowing",
                'count' => $overdue->count(),
                'severity' => 'high',
                'color' => 'red',
                'icon' => '🚨',
                'link' => route('gestation.index') . '?overdue=1',
                'items' => $overdue->take(5)->map(function($mating) use ($today) {
                    $expected = $mating->expected_farrowing_date->startOfDay();
                    $daysOverdue = $today->diffInDays($expected, false); // false means absolute difference
                    return [
                        'id' => $mating->id,
                        'sow_ear_tag' => $mating->female->ear_tag_number ?? 'N/A',
                        'sow_code' => $mating->female->code ?? 'N/A',
                        'expected_date' => $mating->expected_farrowing_date->format('M d, Y'),
                        'days_overdue' => abs($daysOverdue),
                        'link' => route('gestation.edit', $mating->id),
                    ];
                })->toArray(),
            ];
        }
        
        // Due today alerts
        if ($dueToday->count() > 0) {
            $alerts[] = [
                'type' => 'gestation_due_today',
                'title' => 'Gestation Due Today',
                'message' => "{$dueToday->count()} sow(s) are due for farrowing today",
                'count' => $dueToday->count(),
                'severity' => 'high',
                'color' => 'orange',
                'icon' => '🐷',
                'link' => route('gestation.index') . '?due_today=1',
                'items' => $dueToday->take(5)->map(function($mating) {
                    return [
                        'id' => $mating->id,
                        'sow_ear_tag' => $mating->female->ear_tag_number ?? 'N/A',
                        'sow_code' => $mating->female->code ?? 'N/A',
                        'expected_date' => $mating->expected_farrowing_date->format('M d, Y'),
                        'link' => route('gestation.edit', $mating->id),
                    ];
                })->toArray(),
            ];
        }
        
        // Due soon alerts (3 days)
        if ($dueSoon->count() > 0) {
            $alerts[] = [
                'type' => 'gestation_due_soon',
                'title' => 'Gestation Due Soon',
                'message' => "{$dueSoon->count()} sow(s) are due for farrowing in 3 days",
                'count' => $dueSoon->count(),
                'severity' => 'medium',
                'color' => 'yellow',
                'icon' => '⚠️',
                'link' => route('gestation.index') . '?due_soon=1',
                'items' => $dueSoon->take(5)->map(function($mating) {
                    return [
                        'id' => $mating->id,
                        'sow_ear_tag' => $mating->female->ear_tag_number ?? 'N/A',
                        'sow_code' => $mating->female->code ?? 'N/A',
                        'expected_date' => $mating->expected_farrowing_date->format('M d, Y'),
                        'link' => route('gestation.edit', $mating->id),
                    ];
                })->toArray(),
            ];
        }
        
        return [
            'alerts' => $alerts,
            'total_count' => $overdue->count() + $dueToday->count() + $dueSoon->count(),
            'overdue_count' => $overdue->count(),
            'due_today_count' => $dueToday->count(),
            'due_soon_count' => $dueSoon->count(),
        ];
    }

    /**
     * Get feed inventory alerts (low stock, critical stock)
     * 
     * @return array
     */
    public static function getFeedAlerts(): array
    {
        $inventories = FeedInventory::with('feedType')->get();
        $ingredientInventories = IngredientInventory::with('ingredient')->get();
        
        $criticalStock = collect();
        
        // Check feed inventories
        foreach ($inventories as $inventory) {
            $quantity = (float) $inventory->quantity;
            $unit = strtolower($inventory->unit ?? 'kg');
            
            // Skip if quantity is null or zero
            if ($quantity <= 0) {
                continue;
            }
            
            [$threshold, $thresholdLabel] = self::resolveFeedThreshold($unit);
            
            if ($quantity < $threshold) {
                $criticalStock->push([
                    'type' => 'feed',
                    'name' => $inventory->feedType->name ?? 'Unknown',
                    'quantity' => $quantity,
                    'unit' => $inventory->unit,
                    'threshold' => "{$threshold} {$thresholdLabel}",
                    'link' => route('feed-inventory.index'),
                ]);
            }
        }
        
        // Check ingredient inventories
        foreach ($ingredientInventories as $inventory) {
            $quantity = (float) ($inventory->quantity ?? 0);
            $unit = strtolower($inventory->unit ?? 'kg');
            
            // Skip if quantity is null or zero
            if ($quantity <= 0) {
                continue;
            }
            
            [$threshold, $thresholdLabel] = self::resolveFeedThreshold($unit);
            
            if ($quantity < $threshold) {
                $criticalStock->push([
                    'type' => 'ingredient',
                    'name' => $inventory->ingredient->name ?? 'Unknown',
                    'quantity' => $quantity,
                    'unit' => $inventory->unit ?? 'kg',
                    'threshold' => "{$threshold} {$thresholdLabel}",
                    'link' => route('feed-inventory.index'),
                ]);
            }
        }
        
        $alerts = [];
        
        // Critical stock alerts
        if ($criticalStock->count() > 0) {
            $alerts[] = [
                'type' => 'feed_critical',
                'title' => 'Critical Feed Stock',
                'message' => "{$criticalStock->count()} item(s) are below minimum stock levels (<5 bags or <50 kg)",
                'count' => $criticalStock->count(),
                'severity' => 'high',
                'color' => 'red',
                'icon' => '🚨',
                'link' => route('feed-inventory.index'),
                'items' => $criticalStock->take(5)->toArray(),
            ];
        }

        return [
            'alerts' => $alerts,
            'total_count' => $criticalStock->count(),
            'low_stock_count' => 0,
            'critical_stock_count' => $criticalStock->count(),
        ];
    }

    private static function resolveFeedThreshold(string $unit): array
    {
        $unit = strtolower($unit);
        if (str_contains($unit, 'bag')) {
            return [5, 'bags'];
        }

        return [50, 'kg'];
    }

    /**
     * Get total alert count across all categories
     * 
     * @return int
     */
    public static function getTotalAlertCount(): int
    {
        $allAlerts = self::getAllAlerts();

        $weightAlerts = count($allAlerts['weight']['alerts'] ?? []);
        $gestationAlerts = count($allAlerts['gestation']['alerts'] ?? []);
        $feedAlerts = count($allAlerts['feed']['alerts'] ?? []);

        return $weightAlerts + $gestationAlerts + $feedAlerts;
    }

    /**
     * Get high priority alerts only
     * 
     * @return array
     */
    public static function getHighPriorityAlerts(): array
    {
        $allAlerts = self::getAllAlerts();
        $highPriority = [];
        
        foreach ($allAlerts as $category => $data) {
            foreach ($data['alerts'] as $alert) {
                if ($alert['severity'] === 'high') {
                    $highPriority[] = $alert;
                }
            }
        }
        
        return $highPriority;
    }
}

