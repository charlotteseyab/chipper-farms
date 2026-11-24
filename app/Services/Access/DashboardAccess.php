<?php

namespace App\Services\Access;

use App\Models\Mating;
use App\Models\PigDeath;
use App\Models\PigManagement;
use App\Models\PurchaseRequest;
use App\Models\PurchaseStockIntake;
use App\Models\User;
use App\Models\Weaning;
use App\Support\Features\FinanceHubs;

/**
 * What a user may see on a dashboard: the same answer the destination page
 * would give.
 *
 * Dashboards are admitted by role, but their panels show data from modules a
 * farm can revoke per role (Settings → Roles & access). Each panel must
 * therefore ask the same question its destination page asks, or revoking a
 * permission hides the page and leaves its data on the dashboard.
 *
 * FinanceHubs::canOpen() reads a route's `permission:` and `feature:`
 * middleware, but several modules authorise inside the controller through a
 * policy instead, so their routes carry no permission middleware and canOpen()
 * alone would wave them through. Those route prefixes are mapped to their
 * policy here, and the ability follows the action the way the controllers call
 * it: create and import pages authorise `create`, every read page `viewAny`.
 * A view-only grant must not offer "Add a pig", and a record-only grant must.
 */
class DashboardAccess
{
    /** Route-name prefixes whose pages authorise in the controller, by policy model. */
    private const CONTROLLER_GATED = [
        'all-stocks.' => PigManagement::class,
        'pigs.' => PigManagement::class,
        'mating.' => Mating::class,
        'weaning.' => Weaning::class,
        'deaths.' => PigDeath::class,
        'purchase-requests.' => PurchaseRequest::class,
        'purchase-stock-intakes.' => PurchaseStockIntake::class,
    ];

    /** Route-name actions that authorise the policy's `create` ability. */
    private const CREATE_ACTIONS = ['create', 'store'];

    /** @var array<string, bool> */
    private array $memo = [];

    public function __construct(private readonly User $user)
    {
    }

    public static function for(User $user): self
    {
        return new self($user);
    }

    /**
     * May the user see this area's data?
     */
    public function sees(string $area): bool
    {
        return $this->memo['area:'.$area] ??= match ($area) {
            'pigs' => $this->user->can('viewAny', PigManagement::class),
            'weights' => $this->user->hasPermission('view_weights'),
            'health' => $this->user->hasPermission('view_health_records'),
            'breeding' => $this->user->can('viewAny', Mating::class),
            'weaning' => $this->user->can('viewAny', Weaning::class),
            'deaths' => $this->user->can('viewAny', PigDeath::class),
            'raw_materials' => $this->user->hasPermission('view_raw_materials'),
            'feed_production' => $this->user->hasPermission('view_feed_production'),
            'purchase_requests' => $this->user->can('viewAny', PurchaseRequest::class),
            'intakes' => $this->user->can('viewAny', PurchaseStockIntake::class),
            // The farm-wide audit trail is an admin page; everyone else sees
            // only their own entries there, so dashboards follow suit.
            'farm_activity' => $this->user->isSuperuser() || $this->user->isAdmin(),
        };
    }

    /**
     * Would this route open for the user?
     */
    public function canOpen(string $routeName): bool
    {
        return $this->memo['route:'.$routeName] ??= FinanceHubs::canOpen($routeName, $this->user)
            && $this->passesControllerGate($routeName);
    }

    private function passesControllerGate(string $routeName): bool
    {
        foreach (self::CONTROLLER_GATED as $prefix => $model) {
            if (str_starts_with($routeName, $prefix)) {
                $actions = explode('.', substr($routeName, strlen($prefix)));
                $ability = array_intersect($actions, self::CREATE_ACTIONS) !== [] ? 'create' : 'viewAny';

                return $this->user->can($ability, $model);
            }
        }

        return true;
    }
}
