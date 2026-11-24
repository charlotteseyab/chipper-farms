<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Permissions for the livestock screens that used to check role names
 * (pigs, sales, deaths, exits, breeding, purchase requests, purchased stock),
 * so a farm admin can decide which roles may use them.
 *
 * The default grants reproduce exactly what each role could do when these
 * checks were by role name, so nothing changes for anyone until a farm admin
 * edits a role (Settings → Roles & access). Idempotent: run by a migration on
 * deploy and by DatabaseSeeder.
 */
class LivestockAccessPermissionsSeeder extends Seeder
{
    use \Database\Seeders\Concerns\NormalizesSeededRoleNames;

    private const VIEWERS  = ['admin', 'superuser', 'farmmanager', 'dataentry', 'accountant'];
    private const RECORDERS = ['admin', 'superuser', 'farmmanager', 'dataentry'];
    private const MANAGERS = ['admin', 'superuser', 'farmmanager'];
    private const ADMINS   = ['admin', 'superuser'];

    /** @return array<string, array{description: string, roles: list<string>}> */
    public static function definitions(): array
    {
        $managersAndAccounts = [...self::MANAGERS, 'accountant'];

        return [
            // Pigs & stock
            'view_pigs'      => ['description' => 'See pig records and stock lists.', 'roles' => self::VIEWERS],
            'manage_pigs'    => ['description' => 'Add pigs, edit pig records and import stock.', 'roles' => self::RECORDERS],
            'delete_pigs'    => ['description' => 'Delete (archive) pig records.', 'roles' => self::MANAGERS],
            'restore_pigs'   => ['description' => 'Restore archived pig records.', 'roles' => self::ADMINS],
            'promote_pigs'   => ['description' => 'Move pigs between standard and breeding stock.', 'roles' => self::MANAGERS],

            // Sales
            'view_pig_sales'      => ['description' => 'See pig sales.', 'roles' => self::VIEWERS],
            'record_pig_sales'    => ['description' => 'Record, edit and reverse pig sales.', 'roles' => self::MANAGERS],
            'update_sale_payment' => ['description' => 'Update whether a pig sale has been paid.', 'roles' => $managersAndAccounts],
            'delete_pig_sales'    => ['description' => 'Delete pig sales.', 'roles' => self::ADMINS],
            'export_pig_sales'    => ['description' => 'Export pig sales to CSV or PDF.', 'roles' => $managersAndAccounts],

            // Deaths and exits
            'view_pig_deaths'   => ['description' => 'See death records.', 'roles' => self::VIEWERS],
            'record_pig_deaths' => ['description' => 'Record a pig death.', 'roles' => self::RECORDERS],
            'edit_pig_deaths'   => ['description' => 'Edit death records.', 'roles' => self::MANAGERS],
            'delete_pig_deaths' => ['description' => 'Delete death records.', 'roles' => self::ADMINS],
            'export_pig_deaths' => ['description' => 'Export death records.', 'roles' => $managersAndAccounts],
            'view_pig_exits'    => ['description' => 'See transfers out and culls.', 'roles' => self::VIEWERS],
            'record_pig_exits'  => ['description' => 'Record a transfer out or cull.', 'roles' => self::MANAGERS],

            // Breeding: matings, farrowing, weaning
            'view_breeding'   => ['description' => 'See matings, farrowings and weanings.', 'roles' => self::VIEWERS],
            'record_breeding' => ['description' => 'Record matings, pregnancy checks, farrowings and weanings.', 'roles' => self::RECORDERS],
            'manage_breeding' => ['description' => 'Complete or cancel matings; delete farrowings and weanings.', 'roles' => self::MANAGERS],
            'delete_matings'  => ['description' => 'Delete matings.', 'roles' => self::ADMINS],

            // Purchase requests
            'view_purchase_requests'     => ['description' => 'See purchase requests.', 'roles' => self::VIEWERS],
            'create_purchase_requests'   => ['description' => 'Create purchase requests.', 'roles' => self::RECORDERS],
            'edit_purchase_requests'     => ['description' => 'Edit pending purchase requests.', 'roles' => self::MANAGERS],
            'approve_purchase_requests'  => ['description' => 'Approve, decline or delete purchase requests.', 'roles' => self::ADMINS],
            'complete_purchase_requests' => ['description' => 'Mark approved purchase requests complete.', 'roles' => [...self::ADMINS, 'accountant']],

            // Purchased stock intake
            'operate_purchase_intakes' => ['description' => 'Record bought animals: create, receive and quarantine intakes.', 'roles' => self::RECORDERS],
            'approve_purchase_intakes' => ['description' => 'Review quarantine, approve, add to stock or cancel intakes.', 'roles' => self::MANAGERS],
        ];
    }

    /** @return list<string> the livestock permissions a role has by default */
    public static function defaultsFor(?string $roleName): array
    {
        $role = \App\Models\User::normalizeRoleName($roleName);

        return array_keys(array_filter(self::definitions(), fn ($definition) => in_array($role, $definition['roles'], true)));
    }

    /** Only the permission rows (the migration; defaults are applied in code). */
    public static function createPermissions(): void
    {
        foreach (self::definitions() as $name => $definition) {
            Permission::firstOrCreate(['name' => $name], ['description' => $definition['description'], 'module' => 'livestock']);
        }
    }

    public function run(): void
    {
        foreach (self::definitions() as $name => $definition) {
            $permission = Permission::firstOrCreate(['name' => $name], [
                'description' => $definition['description'],
                'module'      => 'livestock',
            ]);

            foreach ($definition['roles'] as $roleName) {
                $this->seededRoleOrCreate($roleName)->permissions()->syncWithoutDetaching([$permission->id]);
            }
        }
    }
}
