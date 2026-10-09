<?php

namespace App\Storefront\Services;

use App\Models\HealthRecord;
use App\Models\MedicationAdministration;
use App\Models\PigManagement;
use App\Models\Storefront\StorefrontListing;
use Illuminate\Support\Carbon;

/**
 * May this animal be offered for sale? (Phase 11, blueprint §35 step 2.)
 *
 * §35 requires the check to confirm the pig is "alive, unsold, not already
 * actively listed, not under withdrawal/health hold, has latest weight if
 * price-by-kg". Before this phase two of those were enforced, one was enforced
 * late, and two could not be enforced at all because the data did not exist.
 *
 * WHY THIS RETURNS REASONS RATHER THAN A BOOLEAN
 * ----------------------------------------------
 * "You cannot list this pig" sends a staff member hunting. Each reason here
 * names the specific thing and, where there is one, the date it clears. The
 * push wizard shows them all at once rather than one per attempt — a person
 * standing in a barn should learn everything that is wrong in one go.
 *
 * WITHDRAWAL IS THE ONE THAT IS NOT A UX MATTER
 * ---------------------------------------------
 * Selling an animal inside its medicine withdrawal period puts drug residue
 * into food. That is a regulatory matter for the farm, and a storefront that
 * lists such an animal with one click makes it likelier. It is checked at push
 * AND again at publish, because a treatment recorded after a listing was
 * drafted must stop that listing going public.
 *
 * A TREATMENT WITH NO WITHDRAWAL RECORDED IS REPORTED, NOT ASSUMED
 * ----------------------------------------------------------------
 * Most treatments genuinely carry no withdrawal period, so a null cannot block
 * a sale — farms would learn to ignore the warning. But a null on a *recent*
 * treatment might equally mean nobody filled the field in. That is surfaced as
 * a caution rather than a blocker, so the gap is visible instead of silently
 * permissive.
 */
class PigListingEligibilityService
{
    /**
     * How recent a treatment has to be for a missing withdrawal period to be
     * worth mentioning. Beyond this, an unrecorded period is very unlikely to
     * still be running.
     */
    public const RECENT_TREATMENT_DAYS = 90;

    /**
     * Health record severities that stop a sale while unresolved.
     *
     * `severe` and `high` only. A mild, unresolved condition — a healing scrape
     * — is not a reason to refuse a sale, and blocking on it would make the
     * check something staff route around.
     */
    public const BLOCKING_SEVERITIES = ['severe', 'high'];

    /**
     * Treatment statuses that mean the animal is still being treated.
     */
    public const OPEN_TREATMENT_STATUSES = ['planned', 'ongoing'];

    /**
     * @return array{eligible: bool, blockers: list<string>, cautions: list<string>}
     */
    /**
     * @param  int|null  $ignoreListingId
     *   A listing whose own pool membership should NOT count as "already
     *   listed". Selling three head out of a pool asks whether each animal may
     *   be sold THROUGH that listing — and every one of them is, by design,
     *   already in it. Without this the pool would block its own sales.
     */
    public function check(
        PigManagement $pig,
        ?string $pricingBasis = null,
        ?int $organizationId = null,
        ?int $ignoreListingId = null,
    ): array
    {
        $blockers = [];
        $cautions = [];

        // --- alive -------------------------------------------------------
        //
        // A sold pig is soft-deleted by the sale spine, so `trashed()` covers
        // both "sold" and "removed". The death check is separate because a pig
        // that died is not merely unavailable — offering it is a different kind
        // of wrong, and the message should say which.
        if ($pig->trashed()) {
            $blockers[] = 'This pig has already been sold or removed from the herd.';
        }

        if ($this->isDead($pig)) {
            $blockers[] = 'This pig is recorded as dead.';
        }

        // --- not already actively listed ---------------------------------
        if ($this->hasActiveListing($pig, $organizationId, $ignoreListingId)) {
            $blockers[] = 'This pig is already on the shop. Edit the existing listing instead of creating a second one.';
        }

        // --- medicine withdrawal -----------------------------------------
        $withdrawal = $this->activeWithdrawal($pig);

        if ($withdrawal !== null) {
            $blockers[] = sprintf(
                'Under medicine withdrawal until %s (%s, given %s). Selling before then puts residue into food.',
                $withdrawal->withdrawal_ends_on->format('d M Y'),
                $withdrawal->medication_name ?: 'treatment',
                Carbon::parse($withdrawal->administration_date)->format('d M Y'),
            );
        }

        foreach ($this->recentTreatmentsWithoutWithdrawal($pig) as $treatment) {
            $cautions[] = sprintf(
                'No withdrawal period was recorded for %s given on %s. Check the label before selling.',
                $treatment->medication_name ?: 'a treatment',
                Carbon::parse($treatment->administration_date)->format('d M Y'),
            );
        }

        // --- health hold --------------------------------------------------
        foreach ($this->blockingHealthRecords($pig) as $record) {
            $blockers[] = sprintf(
                'Unresolved %s health condition%s (recorded %s).',
                $record->severity,
                $record->condition ? ': ' . $record->condition : '',
                Carbon::parse($record->record_date)->format('d M Y'),
            );
        }

        // --- weight, when the price depends on it -------------------------
        //
        // A per-kg listing with no weight cannot be priced at all: CartService
        // refuses it as "priced per kg but has no weight". Catching it here
        // means the staff member finds out while creating the listing rather
        // than a customer finding out at the cart.
        if ($this->isWeightPriced($pricingBasis) && $this->currentWeight($pig) === null) {
            $blockers[] = 'This pig has no recorded weight, so a per-kilo price cannot be worked out. Weigh it first, or set a fixed price.';
        }

        return [
            'eligible' => $blockers === [],
            'blockers' => $blockers,
            'cautions' => $cautions,
        ];
    }

    /**
     * Re-check a drafted listing before it goes public.
     *
     * A listing drafted on Monday and published on Friday may have had a
     * treatment recorded in between. Checking only at push would let that
     * listing go live regardless.
     */
    public function checkListing(StorefrontListing $listing): array
    {
        if (! $listing->isPigBacked() || $listing->source_id === null) {
            return ['eligible' => true, 'blockers' => [], 'cautions' => []];
        }

        $pig = PigManagement::withTrashed()->find($listing->source_id);

        if ($pig === null) {
            return [
                'eligible' => false,
                'blockers' => ['The pig behind this listing no longer exists.'],
                'cautions' => [],
            ];
        }

        $result = $this->check($pig, $listing->pricing_basis, $listing->organization_id);

        // The listing's own existence is not a reason to refuse publishing it.
        $result['blockers'] = array_values(array_filter(
            $result['blockers'],
            fn (string $blocker): bool => ! str_contains($blocker, 'already on the shop'),
        ));

        $result['eligible'] = $result['blockers'] === [];

        return $result;
    }

    /* ------------------------------------------------------------------ */

    protected function isDead(PigManagement $pig): bool
    {
        // The herd model has carried several shapes of this over time. Read
        // whichever exists rather than assuming one, so this cannot silently
        // stop checking after an unrelated schema change.
        foreach (['status', 'health_status', 'life_status'] as $attribute) {
            $value = $pig->getAttribute($attribute);

            if (is_string($value) && in_array(strtolower($value), ['dead', 'deceased'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this pig already spoken for by the shop?
     *
     * TWO SHAPES, ONE QUESTION
     * ------------------------
     * A pig-backed listing used to mean exactly one animal, named by
     * `source_type`/`source_id`. It can now also mean a POOL, with membership
     * in `storefront_listing_pigs`. Both must answer this question or the same
     * animal could be offered twice — once as a single listing and once inside
     * a pool — and be sold twice.
     *
     * Kept as one method rather than two so "already listed" has a single
     * definition. A second, parallel notion of listedness is exactly how the
     * two would drift apart.
     */
    protected function hasActiveListing(PigManagement $pig, ?int $organizationId, ?int $ignoreListingId = null): bool
    {
        $liveStatuses = [
            StorefrontListing::STATUS_DRAFT,
            StorefrontListing::STATUS_PUBLISHED,
            StorefrontListing::STATUS_UNPUBLISHED,
            StorefrontListing::STATUS_RESERVED,
        ];

        // Shape 1 — the legacy single-pig listing.
        $singleListed = StorefrontListing::query()
            ->when($organizationId !== null, fn ($q) => $q->where('organization_id', $organizationId))
            ->when($ignoreListingId !== null, fn ($q) => $q->where('id', '!=', $ignoreListingId))
            ->where('source_type', PigManagement::class)
            ->where('source_id', $pig->getKey())
            ->whereIn('status', $liveStatuses)
            ->exists();

        if ($singleListed) {
            return true;
        }

        // Shape 2 — a member of a live listing's pig pool.
        return \App\Models\Storefront\StorefrontListingPig::query()
            ->where('pig_id', $pig->getKey())
            ->when($ignoreListingId !== null, fn ($q) => $q->where('storefront_listing_id', '!=', $ignoreListingId))
            ->whereIn('status', \App\Models\Storefront\StorefrontListingPig::ACTIVE_STATUSES)
            ->whereHas('listing', function ($q) use ($organizationId, $liveStatuses) {
                $q->whereIn('status', $liveStatuses)
                    ->when($organizationId !== null, fn ($qq) => $qq->where('organization_id', $organizationId));
            })
            ->exists();
    }

    /** The dose that restricts the pig longest, from the shared withdrawal rule. */
    protected function activeWithdrawal(PigManagement $pig): ?MedicationAdministration
    {
        return app(\App\Services\Health\WithdrawalStatusService::class)->forPig($pig)['doses']->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, MedicationAdministration>
     */
    protected function recentTreatmentsWithoutWithdrawal(PigManagement $pig): \Illuminate\Support\Collection
    {
        $since = now()->subDays(self::RECENT_TREATMENT_DAYS)->toDateString();

        return app(\App\Services\Health\WithdrawalStatusService::class)->forPig($pig)['cautions']
            ->filter(fn (MedicationAdministration $dose) => $dose->administration_date->toDateString() >= $since)
            ->take(3)
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, HealthRecord>
     */
    protected function blockingHealthRecords(PigManagement $pig): \Illuminate\Support\Collection
    {
        return HealthRecord::query()
            ->where('pig_id', $pig->getKey())
            ->notVoided()
            ->whereNull('resolved_at')
            ->where(function ($q) {
                $q->whereIn('severity', self::BLOCKING_SEVERITIES)
                    ->orWhereIn('treatment_status', self::OPEN_TREATMENT_STATUSES);
            })
            ->orderByDesc('record_date')
            ->limit(3)
            ->get();
    }

    protected function isWeightPriced(?string $pricingBasis): bool
    {
        return in_array($pricingBasis, [
            StorefrontListing::BASIS_LIVE,
            StorefrontListing::BASIS_CARCASS,
        ], true);
    }

    protected function currentWeight(PigManagement $pig): ?string
    {
        $weight = $pig->current_weight_kg_cache;

        if ($weight === null || bccomp((string) $weight, '0', 4) <= 0) {
            return null;
        }

        return (string) $weight;
    }
}
