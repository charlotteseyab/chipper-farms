<?php

namespace App\View\Components\Dashboard;

use App\Services\Dashboard\DashboardLivestockMetrics;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class LivestockOverview extends Component
{
    public array $metrics;

    public function __construct(DashboardLivestockMetrics $metrics)
    {
        $user = auth()->user();
        $organizationId = $user?->isSuperuser() ? null : ($user?->organization_id ? (int) $user->organization_id : null);
        $this->metrics = $metrics->summary($organizationId, $user?->isSuperuser() === true);
    }

    public function render(): View
    {
        return view('components.dashboard.livestock-overview');
    }
}
