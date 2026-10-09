<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Http\Requests\Health\CloseScheduledDoseRequest;
use App\Models\ScheduledDose;
use App\Services\Health\ScheduledDoseService;

/** Mark a scheduled dose missed or cancelled. Giving one goes through the Record dose form. */
class ScheduledDoseController extends Controller
{
    public function close(CloseScheduledDoseRequest $request, ScheduledDose $scheduledDose, ScheduledDoseService $service)
    {
        $data = $request->validated();
        try {
            $service->close($request->user(), $scheduledDose, $data['status'], $data['reason']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Dose marked '.$data['status'].'. No stock was taken.');
    }
}
