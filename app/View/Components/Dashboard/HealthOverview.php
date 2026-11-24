<?php

namespace App\View\Components\Dashboard;

use App\Services\Dashboard\DashboardHealthMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The herd-health section shared by the farm dashboards. Shown to anyone who
 * can view health records on a farm; not on the platform view, where health
 * records belong to individual farms.
 */
class HealthOverview extends Component
{
    /** @param  array|null  $health  figures a dashboard already computed, so they are not worked out twice */
    public function __construct(DashboardHealthMetrics $metrics, public ?array $health = null)
    {
        $user = auth()->user();

        if (! $user || $user->isSuperuser() || ! $user->organization_id || ! $user->hasPermission('view_health_records')) {
            $this->health = null;

            return;
        }

        $this->health ??= $metrics->summary(CarbonImmutable::now());
    }

    public function shouldRender(): bool
    {
        return $this->health !== null;
    }

    public function render(): View
    {
        return view('components.dashboard.health-overview');
    }
}
