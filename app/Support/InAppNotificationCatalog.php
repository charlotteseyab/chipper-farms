<?php

namespace App\Support;

use App\Support\EmailTemplateCatalog;

final class InAppNotificationCatalog
{
    /** @return array<string, array{module:string,audience:string,permissions:array<int,string>}> */
    public static function definitions(): array
    {
        $catalogue = [];
        foreach (EmailTemplateCatalog::definitions() as $key => $emailDefinition) {
            $prefix = explode('.', $key, 2)[0];
            $catalogue[$key] = [
                'module' => self::module($prefix),
                'audience' => ($emailDefinition['audience'] ?? 'staff') === 'customer' ? 'buyer' : 'staff',
                'permissions' => self::permissions($prefix),
            ];
        }

        // In-app-only operational events that do not warrant an email.
        $catalogue['pigs.stock_added'] = [
            'module' => 'Pig operations',
            'audience' => 'staff',
            'permissions' => self::permissions('pigs'),
        ];
        // Health reminders that are in-app only (decision D9).
        foreach (['health.dose_overdue', 'health.follow_up_due', 'health.batch_expiring'] as $key) {
            $catalogue[$key] = [
                'module' => self::module('health'),
                'audience' => 'staff',
                'permissions' => self::permissions('health'),
            ];
        }
        $catalogue['system.action_completed'] = [
            'module' => 'System operations',
            'audience' => 'staff',
            'permissions' => [],
        ];

        return $catalogue;
    }

    private static function module(string $prefix): string
    {
        return match ($prefix) {
            'pigs' => 'Pig operations',
            'facilities' => 'Structures and rooms',
            'breeding', 'operations' => 'Breeding and lifecycle',
            'health' => 'Health and medication',
            'weights' => 'Weight management',
            'feed', 'procurement' => 'Feed and procurement',
            'inventory' => 'Inventory and costing',
            'sales' => 'Sales',
            'storefront' => 'Storefront',
            'finance' => 'Finance',
            'users' => 'Users and security',
            default => 'System operations',
        };
    }

    private static function permissions(string $prefix): array
    {
        return match ($prefix) {
            'pigs' => ['view_pigs', 'view_all_stocks', 'view_breeding_stocks'],
            'facilities' => ['view_facilities'],
            'breeding', 'operations' => ['view_mating', 'view_gestation', 'view_farrowing', 'view_weaning'],
            'health' => ['view_health_records'],
            'weights' => ['view_weights'],
            'feed', 'procurement' => ['view_feed_production', 'view_purchase_requests', 'view_raw_materials'],
            'inventory' => ['view_inventory_value'],
            'sales' => ['view_sales'],
            'storefront' => ['view_storefront_dashboard', 'view_storefront_orders', 'view_storefront_quotes'],
            'finance' => ['view_finance'],
            'users' => ['manage_users'],
            default => ['view_system_reports'],
        };
    }
}
