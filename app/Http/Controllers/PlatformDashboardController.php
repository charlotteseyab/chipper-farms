<?php

namespace App\Http\Controllers;

use App\Services\Reports\PlatformReportingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformDashboardController extends Controller
{
    public function __invoke(Request $request, PlatformReportingService $reports): View
    {
        abort_unless($request->user()?->isSuperuser(), 403);
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $from = CarbonImmutable::parse($validated['from'] ?? now()->startOfMonth()->toDateString());
        $to = CarbonImmutable::parse($validated['to'] ?? now()->toDateString());

        return view('superuser.platform-dashboard', $reports->build($from, $to));
    }
}
