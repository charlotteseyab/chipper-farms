<?php

namespace App\Services\Finance\Tax;

use App\Models\Finance\TaxProfile;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;

/**
 * Picks the right tax profile for a transaction (Phase 2).
 *
 * WHY RESOLUTION IS BY DATE, NOT "THE CURRENT ONE"
 * ------------------------------------------------
 * A transaction dated before 2026 must resolve to the pre-2026 cascading
 * profile, not to today's. Back-dated entries are ordinary in farm accounting —
 * a receipt surfaces a fortnight late — and resolving them against today's rates
 * would post a figure the original document does not show.
 *
 * Every method therefore takes the transaction date and defaults to today only
 * when the caller genuinely has no date.
 *
 * MISSING CONFIGURATION IS SURFACED, NOT GUESSED
 * -----------------------------------------------
 * When no profile is effective for a date, this returns null rather than
 * silently falling back to zero tax. Blueprint §54 lists "tax profile missing"
 * as a state that must route the user to settings, and §46 requires it to block
 * taxable posting rather than post something wrong. A silent zero would look
 * exactly like a legitimately exempt sale.
 */
class TaxProfileResolver
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    public function defaultForSales(
        \DateTimeInterface|string|null $on = null,
        Organization|int|null $organization = null,
    ): ?TaxProfile {
        return $this->query($organization, $on)
            ->forSales()
            ->where('is_default_sales', true)
            ->with('components')
            ->orderByDesc('effective_from')
            ->first();
    }

    public function defaultForPurchases(
        \DateTimeInterface|string|null $on = null,
        Organization|int|null $organization = null,
    ): ?TaxProfile {
        return $this->query($organization, $on)
            ->forPurchases()
            ->where('is_default_purchase', true)
            ->with('components')
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * A specific profile by code, as it stood on a date.
     *
     * "Ghana VAT standard" exists in more than one effective period, so code
     * alone is not enough to identify a definition.
     */
    public function byCode(
        string $code,
        \DateTimeInterface|string|null $on = null,
        Organization|int|null $organization = null,
    ): ?TaxProfile {
        return $this->query($organization, $on)
            ->where('code', $code)
            ->with('components')
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Everything selectable on a date, for a settings or line-entry dropdown.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, TaxProfile>
     */
    public function selectable(
        string $appliesTo = TaxProfile::APPLIES_BOTH,
        \DateTimeInterface|string|null $on = null,
        Organization|int|null $organization = null,
    ) {
        $query = $this->query($organization, $on)->with('components');

        if ($appliesTo === TaxProfile::APPLIES_SALE) {
            $query->forSales();
        } elseif ($appliesTo === TaxProfile::APPLIES_PURCHASE) {
            $query->forPurchases();
        }

        return $query->orderBy('treatment')->orderBy('name')->get();
    }

    /**
     * True when the organisation has no usable sales tax configuration for a
     * date. Drives the "tax setup needed" attention item in blueprint §54.
     */
    public function salesConfigurationMissing(
        \DateTimeInterface|string|null $on = null,
        Organization|int|null $organization = null,
    ): bool {
        return $this->defaultForSales($on, $organization) === null;
    }

    /* ------------------------------------------------------------------ */

    protected function query(Organization|int|null $organization, \DateTimeInterface|string|null $on)
    {
        $organizationId = $this->resolveOrganizationId($organization);
        $date           = $this->normaliseDate($on);

        return TaxProfile::query()
            ->when(
                $organizationId !== null,
                fn ($q) => $q->forOrganization($organizationId),
                // No organisation context resolves to nothing rather than to
                // another tenant's profiles.
                fn ($q) => $q->whereRaw('1 = 0'),
            )
            ->active()
            ->effectiveOn($date);
    }

    protected function normaliseDate(\DateTimeInterface|string|null $on): string
    {
        if ($on === null) {
            return CarbonImmutable::now()->toDateString();
        }

        return $on instanceof \DateTimeInterface
            ? CarbonImmutable::instance($on)->toDateString()
            : CarbonImmutable::parse($on)->toDateString();
    }

    protected function resolveOrganizationId(Organization|int|null $organization): ?int
    {
        if ($organization instanceof Organization) {
            return (int) $organization->id;
        }

        if (is_int($organization)) {
            return $organization;
        }

        return $this->currentOrganization->get()?->id;
    }
}
