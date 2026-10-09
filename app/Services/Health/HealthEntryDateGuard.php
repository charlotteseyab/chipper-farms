<?php

namespace App\Services\Health;

use App\Models\PigManagement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Date sanity for health and medication entries.
 *
 * An entry cannot be in the future, before the pig was born or arrived, or
 * after it was sold or died. Entries for a pig that has already left are
 * allowed for earlier dates, by managers only, with a reason (decision D3).
 */
class HealthEntryDateGuard
{
    public function check(User $actor, PigManagement $pig, string $date, ?string $lateEntryReason, string $field): void
    {
        $day = CarbonImmutable::parse($date)->startOfDay();
        $fail = fn (string $message) => throw ValidationException::withMessages([$field => $message]);

        if ($day->gt(CarbonImmutable::today())) {
            $fail('The date cannot be in the future.');
        }
        if ($pig->date_of_birth && $day->lt($pig->date_of_birth->startOfDay())) {
            $fail('The date is before this pig was born ('.$pig->date_of_birth->format('d M Y').').');
        }
        if ($pig->acquisition_date && $day->lt($pig->acquisition_date->startOfDay())) {
            $fail('The date is before this pig arrived on the farm ('.$pig->acquisition_date->format('d M Y').').');
        }

        $left = $this->leftOn($pig);
        if ($left === null) {
            return;
        }
        if ($day->gt($left['date'])) {
            $fail("The date is after this pig {$left['how']} on {$left['date']->format('d M Y')}.");
        }
        if (! $this->mayRecordForDeparted($actor)) {
            $fail("This pig {$left['how']} on {$left['date']->format('d M Y')}. Only a farm manager can add records for it.");
        }
        if (mb_strlen(trim((string) $lateEntryReason)) < 5) {
            throw ValidationException::withMessages(['late_entry_reason' => "This pig {$left['how']}. Say why this record is being added now."]);
        }
    }

    /** True when the pig has been sold or has died. */
    public function hasLeft(PigManagement $pig): bool
    {
        return $this->leftOn($pig) !== null;
    }

    public function mayRecordForDeparted(User $actor): bool
    {
        return $actor->isSuperuser() || $actor->isAdmin() || $actor->isManager() || $actor->hasRoleName('manager');
    }

    /** @return array{date: CarbonImmutable, how: string}|null */
    private function leftOn(PigManagement $pig): ?array
    {
        $sold = $pig->sales()->orderBy('sale_date')->value('sale_date');
        $died = $pig->deaths()->orderBy('death_date')->value('death_date');

        $exit = $pig->herdExit()->first();
        $events = array_filter([
            'was sold' => $sold ? CarbonImmutable::parse($sold)->startOfDay() : null,
            'died'     => $died ? CarbonImmutable::parse($died)->startOfDay() : null,
            ($exit?->exit_type === \App\Models\PigExit::CULL ? 'was culled' : 'was transferred out')
                => $exit ? CarbonImmutable::parse($exit->exit_date)->startOfDay() : null,
        ]);
        // Archived without a sale or death record: the removal date is all we have.
        if ($events === [] && $pig->trashed()) {
            $events['was removed from the herd'] = CarbonImmutable::parse($pig->deleted_at)->startOfDay();
        }
        if ($events === []) {
            return null;
        }
        asort($events);

        return ['date' => reset($events), 'how' => array_key_first($events)];
    }
}
