<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Services\Health\HealthAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Vaccinations, deworming, treatments, follow-ups and withdrawals: overdue, due today and coming up. */
class HealthAlertController extends Controller
{
    public function __invoke(Request $request, HealthAlertService $alerts)
    {
        abort_unless($request->user()->hasPermission('view_health_records'), 403);

        $data = $request->validate([
            'type'  => ['nullable', Rule::in(array_keys(HealthAlertService::TYPES))],
            'state' => ['nullable', Rule::in(array_keys(HealthAlertService::STATES))],
            'days'  => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $days = (int) ($data['days'] ?? HealthAlertService::defaultDays());
        $all = $alerts->alerts(CarbonImmutable::now(), $days);

        $shown = $all
            ->when($data['type'] ?? null, fn ($c, $type) => $c->where('type', $type))
            ->when($data['state'] ?? null, fn ($c, $state) => $c->where('state', $state));

        return view('health.alerts', [
            'alerts'  => $shown->groupBy('state'),
            'counts'  => $all->countBy('type')->all(),
            'states'  => $all->countBy('state')->all(),
            'total'   => $all->count(),
            'type'    => $data['type'] ?? null,
            'state'   => $data['state'] ?? null,
            'days'    => $days,
        ]);
    }
}
