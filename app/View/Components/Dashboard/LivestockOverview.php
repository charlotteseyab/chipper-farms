<?php

namespace App\View\Components\Dashboard;

use App\Services\Dashboard\DashboardLivestockMetrics;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class LivestockOverview extends Component
{
    public array $metrics = [];

    private bool $visible = false;

    public function __construct(DashboardLivestockMetrics $metrics)
    {
        $user = auth()->user();

        // Herd figures are pig records: shown only to those who may open the
        // pig list, on every dashboard that includes this panel.
        if (! $user || ! \App\Services\Access\DashboardAccess::for($user)->sees('pigs')) {
            return;
        }

        $this->visible = true;
        $organizationId = $user?->isSuperuser() ? null : ($user?->organization_id ? (int) $user->organization_id : null);
        $this->metrics = $metrics->summary($organizationId, $user?->isSuperuser() === true);
    }

    public function shouldRender(): bool
    {
        return $this->visible;
    }

    public function render(): View
    {
        return view('components.dashboard.livestock-overview');
    }
}
