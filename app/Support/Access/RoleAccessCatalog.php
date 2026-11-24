<?php

namespace App\Support\Access;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Services\Organizations\OrganizationFeatureService;
use App\Support\Features\FeatureCatalog;

/**
 * What a farm admin can give each role, grouped into the modules shown on
 * Settings → Roles & access.
 *
 * Each module lists its permissions; the ones starting with view_ make up
 * "View", all of them "Full". Finance and the storefront take every permission
 * of that kind from the permissions table, so one added later is offered
 * without a change here. Permissions not offered here are kept as they are
 * when a role is saved.
 */
class RoleAccessCatalog
{
    /** Roles a farm admin may change; admin itself, superuser and customer are not. */
    public const EDITABLE_ROLES = ['farmmanager', 'dataentry', 'accountant', 'treasurer', 'salesperson', 'mill_operator'];

    /** Platform powers, never given by a farm. */
    public const PLATFORM_ONLY = ['manage_feature_flags', 'process_outbox'];

    /** @return array<string, array{label: string, icon: string, description: string, feature: ?string, permissions: list<string>}> */
    public static function modules(): array
    {
        $modules = [
            'pigs' => [
                'label' => 'Pigs & stock',
                'icon' => 'fa-solid fa-paw',
                'description' => 'Pig records, stock lists, imports and purchased stock intake.',
                'feature' => null,
                'permissions' => ['view_pigs', 'manage_pigs', 'delete_pigs', 'restore_pigs', 'promote_pigs', 'operate_purchase_intakes', 'approve_purchase_intakes'],
            ],
            'breeding' => [
                'label' => 'Breeding & farrowing',
                'icon' => 'fa-solid fa-venus-mars',
                'description' => 'Matings, gestation, farrowing, piglets and weaning.',
                'feature' => null,
                'permissions' => ['view_breeding', 'view_farrowing', 'record_breeding', 'manage_farrowing', 'manage_breeding', 'manage_piglet_deaths', 'delete_matings'],
            ],
            'weights' => [
                'label' => 'Weights',
                'icon' => 'fa-solid fa-weight-scale',
                'description' => 'Weight records, reports and alerts.',
                'feature' => null,
                'permissions' => ['view_weights', 'record_weights'],
            ],
            'health' => [
                'label' => 'Health & medication',
                'icon' => 'fa-solid fa-heart-pulse',
                'description' => 'Health cases, treatments, vet visits and the medicine catalogue.',
                'feature' => null,
                'permissions' => ['view_health_records', 'manage_health_records', 'administer_medication', 'manage_vet_visits', 'export_health_reports', 'finalise_vet_visits', 'manage_treatment_courses', 'manage_medicine_catalogue', 'manage_medication_records', 'correct_health_records', 'delete_health_records'],
            ],
            'mortality' => [
                'label' => 'Mortality',
                'icon' => 'fa-solid fa-skull-crossbones',
                'description' => 'Death records.',
                'feature' => null,
                'permissions' => ['view_pig_deaths', 'record_pig_deaths', 'edit_pig_deaths', 'export_pig_deaths', 'delete_pig_deaths'],
            ],
            'sales' => [
                'label' => 'Pig sales & transfers',
                'icon' => 'fa-solid fa-coins',
                'description' => 'Pig sales, payments, transfers out and culls.',
                'feature' => null,
                'permissions' => ['view_pig_sales', 'view_pig_exits', 'record_pig_sales', 'update_sale_payment', 'export_pig_sales', 'record_pig_exits', 'delete_pig_sales'],
            ],
            'facilities' => [
                'label' => 'Structures & rooms',
                'icon' => 'fa-solid fa-warehouse',
                'description' => 'Buildings and rooms.',
                'feature' => null,
                'permissions' => ['view_facilities', 'manage_facilities'],
            ],
            'feed' => [
                'label' => 'Feed',
                'icon' => 'fa-solid fa-bowl-food',
                'description' => 'Feeding, mixing, raw materials and feed sales.',
                'feature' => null,
                'permissions' => ['view_feed_production', 'view_raw_materials', 'view_feed_sales', 'manage_feed_issues', 'manage_feed_production', 'manage_raw_materials', 'record_feed_sales'],
            ],
            'purchasing' => [
                'label' => 'Purchase requests',
                'icon' => 'fa-solid fa-cart-shopping',
                'description' => 'Requests to buy, their approval and completion.',
                'feature' => null,
                'permissions' => ['view_purchase_requests', 'create_purchase_requests', 'edit_purchase_requests', 'approve_purchase_requests', 'complete_purchase_requests'],
            ],
        ];

        $claimed = array_merge(...array_column($modules, 'permissions'));
        $fromTable = fn (string $module) => Permission::query()->where('module', $module)
            ->whereNotIn('name', [...$claimed, ...self::PLATFORM_ONLY])
            ->orderBy('name')->pluck('name')
            ->sortBy(fn ($name) => str_starts_with($name, 'view_') ? 0 : 1)->values()->all();

        $modules['finance'] = [
            'label' => 'Finance',
            'icon' => 'fa-solid fa-sack-dollar',
            'description' => 'Money in and out, bank, loans, suppliers, reports and accounting.',
            'feature' => 'finance',
            'permissions' => $fromTable('finance'),
        ];
        $modules['storefront'] = [
            'label' => 'Storefront',
            'icon' => 'fa-solid fa-store',
            'description' => 'Online shop listings, orders, quotes and reports.',
            'feature' => FeatureCatalog::STOREFRONT_ENABLED,
            'permissions' => $fromTable('storefront'),
        ];

        return $modules;
    }

    /** The modules a farm has switched on. */
    public static function availableFor(Organization $organization): array
    {
        $features = app(OrganizationFeatureService::class);

        return array_filter(self::modules(), function (array $module) use ($features, $organization) {
            if ($module['permissions'] === []) {
                return false;
            }

            return match ($module['feature']) {
                null => true,
                'finance' => collect(FeatureCatalog::inDomain(FeatureCatalog::DOMAIN_FINANCE))
                    ->contains(fn ($key) => $features->enabled($key, $organization)),
                default => $features->enabled($module['feature'], $organization),
            };
        });
    }

    /** @return list<string> */
    public static function viewPermissions(array $module): array
    {
        return array_values(array_filter($module['permissions'], fn ($name) => str_starts_with($name, 'view_')));
    }

    /** none, view, full or custom, for the permissions a role holds. */
    public static function level(array $module, array $granted): string
    {
        $held = array_values(array_intersect($module['permissions'], $granted));
        $view = self::viewPermissions($module);
        sort($held);
        sort($view);

        return match (true) {
            $held === [] => 'none',
            count($held) === count($module['permissions']) => 'full',
            $held === $view => 'view',
            default => 'custom',
        };
    }

    public static function isEditableRole(?string $roleName): bool
    {
        return in_array(User::normalizeRoleName($roleName), self::EDITABLE_ROLES, true);
    }

    public static function permissionLabel(string $name): string
    {
        return ucfirst(str_replace('_', ' ', $name));
    }
}
