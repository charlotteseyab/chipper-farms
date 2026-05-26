<?php

namespace App\Services\Weaning;

use App\Models\PigManagement;
use App\Models\User;
use App\Notifications\PigletWeaningDueNotification;

/**
 * Computes weaning alerts and dispatches notifications to privileged users.
 *
 * Extracted from PigManagementController::getWeaningAlerts (was line 1359).
 * The original was a private helper; keeping it as a service keeps the
 * notification side-effect testable and mockable.
 *
 * Alert buckets:
 *   - upcoming : exactly 2 days before target weaning date
 *   - today    : due within 0–1 days
 *   - overdue  : >= 2 days past target weaning date
 *
 * Target weaning age is config-driven (breeding.cycle.birth_to_weaning_days,
 * default 45).
 *
 * Notifiable roles: admin, superuser, manager, dataentry. Accountant is NOT
 * notified — weaning is an operational concern, not a finance one.
 */
class WeaningAlertService
{
    /**
     * @return array{upcoming: array, today: array, overdue: array}
     */
    public function collectAlerts(): array
    {
        $weaningAge = (int) config('breeding.cycle.birth_to_weaning_days', 45);
        $today      = now()->startOfDay();

        $alerts = [
            'upcoming' => [],
            'today'    => [],
            'overdue'  => [],
        ];

        $unweaned = PigManagement::active()
            ->whereNotNull('date_of_birth')
            ->whereDoesntHave('weaning')
            ->with(['mother', 'father'])
            ->get();

        $notifiables = User::whereHas('role', function ($q) {
            $q->whereIn('name', ['admin', 'superuser', 'manager', 'dataentry']);
        })->get();

        foreach ($unweaned as $pig) {
            $targetDate       = $pig->date_of_birth->copy()->addDays($weaningAge);
            $daysUntilWeaning = $today->diffInDays($targetDate, false);

            if ($daysUntilWeaning === 2) {
                $alerts['upcoming'][] = [
                    'pig'         => $pig,
                    'target_date' => $targetDate,
                    'days_until'  => 2,
                    'message'     => 'Weaning due in 2 days',
                ];
                $this->notifyAll($notifiables, $pig, 'upcoming', 2);
            } elseif ($daysUntilWeaning <= 1 && $daysUntilWeaning >= 0) {
                $alerts['today'][] = [
                    'pig'         => $pig,
                    'target_date' => $targetDate,
                    'days_until'  => $daysUntilWeaning,
                    'message'     => $daysUntilWeaning === 0 ? 'Weaning due today' : 'Weaning due tomorrow',
                ];
                $this->notifyAll($notifiables, $pig, 'due_today', $daysUntilWeaning);
            } elseif ($daysUntilWeaning <= -2) {
                $daysOverdue = abs($daysUntilWeaning);
                $alerts['overdue'][] = [
                    'pig'          => $pig,
                    'target_date'  => $targetDate,
                    'days_overdue' => $daysOverdue,
                    'message'      => "Weaning overdue by {$daysOverdue} days",
                ];
                $this->notifyAll($notifiables, $pig, 'overdue', $daysOverdue);
            }
        }

        return $alerts;
    }

    protected function notifyAll(iterable $users, PigManagement $pig, string $status, int $days): void
    {
        foreach ($users as $user) {
            $user->notify(new PigletWeaningDueNotification($pig, $status, $days));
        }
    }
}