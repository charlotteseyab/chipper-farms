<?php

namespace App\Services\Finance\Overview;

use App\Models\DomainEvent;
use App\Models\Organization;
use App\Models\Storefront\StorefrontOrder;
use App\Services\Finance\Tax\TaxProfileResolver;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Schema;

/**
 * The consolidated "something needs a human" queue (Phase 3, blueprint §54,
 * prompt §28).
 *
 * WHY THIS EXISTS
 * ---------------
 * The failure states in this system are currently scattered: a failed outbox
 * event lives in `domain_events`, a blocked COD completion lives in a
 * storefront order event note, a pending refund lives on the order, a missing
 * tax profile lives in an absence of rows. Nobody looks in four places, so in
 * practice these are discovered when a customer complains.
 *
 * TWO AUDIENCES, ONE SOURCE
 * -------------------------
 * Every item carries both a plain-language `message` for a farm operator and a
 * `detail` for someone who can actually fix it. Blueprint §54 is explicit that
 * technical text must not be the primary user message — "Finance action needs
 * attention" is what an owner should read, with the event id and subscriber
 * error available underneath to whoever holds the permission.
 *
 * EVERY QUERY IS ORGANISATION-SCOPED
 * -----------------------------------
 * Without exception. The dashboard this replaces counted `domain_events` with
 * no organisation filter at all (see the Phase 3 report), so one farm's
 * accountant saw another farm's failure count. Counts leak less than records,
 * but they are still someone else's data and they make the number wrong for the
 * person reading it.
 *
 * DEFENSIVE ABOUT SCHEMA
 * ----------------------
 * Sources are probed with `Schema::hasTable` before querying. This service is
 * consumed by the dashboard, which every finance user hits on login; a missing
 * table in a partially-migrated environment must degrade to "no items from that
 * source", not to a 500 that locks everyone out of finance.
 */
class NeedsAttentionService
{
    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_WARNING  = 'warning';
    public const SEVERITY_INFO     = 'info';

    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected TaxProfileResolver $taxProfiles,
    ) {
    }

    /**
     * @return list<array{
     *     key:string, severity:string, count:int, message:string,
     *     detail:string, route:?string, permission:?string
     * }>
     */
    public function items(Organization|int|null $organization = null): array
    {
        $organizationId = $this->resolveOrganizationId($organization);

        if ($organizationId === null) {
            return [];
        }

        $items = array_filter([
            $this->failedFinancePostings($organizationId),
            $this->stuckFinancePostings($organizationId),
            $this->blockedCodCompletions($organizationId),
            $this->pendingRefunds($organizationId),
            $this->ordersAwaitingConfirmation($organizationId),
            $this->missingTaxConfiguration($organizationId),
            $this->unsettledStorefrontPayments($organizationId),
        ]);

        // Critical first, then by size — the biggest problem of the worst kind
        // should be the first thing on the screen.
        $rank = [
            self::SEVERITY_CRITICAL => 0,
            self::SEVERITY_WARNING  => 1,
            self::SEVERITY_INFO     => 2,
        ];

        usort($items, static function (array $a, array $b) use ($rank): int {
            return [$rank[$a['severity']], -$a['count']] <=> [$rank[$b['severity']], -$b['count']];
        });

        return array_values($items);
    }

    public function total(Organization|int|null $organization = null): int
    {
        return array_sum(array_column($this->items($organization), 'count'));
    }

    public function hasCritical(Organization|int|null $organization = null): bool
    {
        foreach ($this->items($organization) as $item) {
            if ($item['severity'] === self::SEVERITY_CRITICAL) {
                return true;
            }
        }

        return false;
    }

    /* ------------------------------------------------------------------ */
    /* Sources                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Outbox events that have exhausted their retries.
     *
     * Critical because the source transaction succeeded — a sale was recorded,
     * stock moved — and its accounting consequence did not. The books are
     * incomplete until someone replays it.
     */
    protected function failedFinancePostings(int $organizationId): ?array
    {
        if (! Schema::hasTable('domain_events')) {
            return null;
        }

        $count = DomainEvent::query()
            ->where('organization_id', $organizationId)
            ->where('status', DomainEvent::STATUS_FAILED)
            ->count();

        if ($count === 0) {
            return null;
        }

        return [
            'key'        => 'finance.failed_postings',
            'severity'   => self::SEVERITY_CRITICAL,
            'count'      => $count,
            'message'    => $count === 1
                ? 'One bookkeeping action could not be completed.'
                : "{$count} bookkeeping actions could not be completed.",
            'detail'     => 'Domain events in FAILED state. The originating transaction succeeded but '
                . 'its finance posting did not, so the ledger is incomplete. Fix the cause, then replay '
                . 'the event — posting is idempotent, so a replay cannot double-post.',
            'route'      => null,
            'permission' => 'view_finance',
        ];
    }

    /**
     * Events claimed by a worker that never finished.
     *
     * Distinct from failed: nothing has errored, so nothing will retry these on
     * its own. A worker died mid-flight and the row is still marked as being
     * worked on. Without surfacing it, it sits there indefinitely.
     */
    protected function stuckFinancePostings(int $organizationId): ?array
    {
        if (! Schema::hasTable('domain_events')) {
            return null;
        }

        $threshold = now()->subMinutes(30);

        $count = DomainEvent::query()
            ->where('organization_id', $organizationId)
            ->where('status', DomainEvent::STATUS_PROCESSING)
            ->whereNotNull('claimed_at')
            ->where('claimed_at', '<', $threshold)
            ->count();

        if ($count === 0) {
            return null;
        }

        return [
            'key'        => 'finance.stuck_postings',
            'severity'   => self::SEVERITY_WARNING,
            'count'      => $count,
            'message'    => 'Some bookkeeping is taking longer than expected.',
            'detail'     => "{$count} domain event(s) have been in PROCESSING for over 30 minutes. "
                . 'A worker most likely stopped mid-flight. These do not retry by themselves because '
                . 'nothing has errored — the stale-claim recovery job releases them.',
            'route'      => null,
            'permission' => 'view_finance',
        ];
    }

    /**
     * COD orders that reached fulfilment but cannot settle.
     *
     * This is the CF-P0-009 gate doing its job: completion is refused when the
     * underlying sale has not posted. Correct behaviour, but it leaves an order
     * a human must chase, and previously the only trace was a note on the order.
     */
    protected function blockedCodCompletions(int $organizationId): ?array
    {
        if (! Schema::hasTable('storefront_orders')) {
            return null;
        }

        $count = StorefrontOrder::query()
            ->where('organization_id', $organizationId)
            ->where('payment_method', StorefrontOrder::METHOD_COD)
            ->whereIn('status', [
                StorefrontOrder::STATUS_READY_FOR_PICKUP,
                StorefrontOrder::STATUS_OUT_FOR_DELIVERY,
            ])
            ->where('payment_status', '!=', StorefrontOrder::PAY_PAID)
            ->count();

        if ($count === 0) {
            return null;
        }

        return [
            'key'        => 'storefront.cod_awaiting_cash',
            'severity'   => self::SEVERITY_WARNING,
            'count'      => $count,
            'message'    => $count === 1
                ? 'One cash order is waiting for payment to be confirmed.'
                : "{$count} cash orders are waiting for payment to be confirmed.",
            'detail'     => 'COD orders that are ready or dispatched but not yet marked paid. '
                . 'Completion stays blocked until the underlying sale has posted to finance and the '
                . 'cash has been confirmed received.',
            'route'      => null,
            'permission' => 'manage_storefront_orders',
        ];
    }

    /**
     * Refunds submitted to the provider that have not settled.
     *
     * Critical past a threshold, because the customer has been told a refund is
     * coming and the money has not moved.
     */
    protected function pendingRefunds(int $organizationId): ?array
    {
        if (! Schema::hasTable('storefront_orders')) {
            return null;
        }

        $count = StorefrontOrder::query()
            ->where('organization_id', $organizationId)
            ->where('payment_status', StorefrontOrder::PAY_REFUND_PENDING)
            ->count();

        if ($count === 0) {
            return null;
        }

        $stale = StorefrontOrder::query()
            ->where('organization_id', $organizationId)
            ->where('payment_status', StorefrontOrder::PAY_REFUND_PENDING)
            ->where('updated_at', '<', now()->subDays(3))
            ->count();

        return [
            'key'        => 'storefront.refunds_pending',
            'severity'   => $stale > 0 ? self::SEVERITY_CRITICAL : self::SEVERITY_INFO,
            'count'      => $count,
            'message'    => $stale > 0
                ? "{$stale} refund(s) have been pending for more than three days."
                : "{$count} refund(s) are awaiting the payment provider.",
            'detail'     => 'Refunds stay pending until the provider confirms settlement. '
                . 'Anything older than three days should be checked against the provider dashboard — '
                . 'do not resubmit, as that risks refunding twice.',
            'route'      => null,
            'permission' => 'refund_storefront_orders',
        ];
    }

    protected function ordersAwaitingConfirmation(int $organizationId): ?array
    {
        if (! Schema::hasTable('storefront_orders')) {
            return null;
        }

        $count = StorefrontOrder::query()
            ->where('organization_id', $organizationId)
            ->where('status', StorefrontOrder::STATUS_PENDING_CONFIRMATION)
            ->count();

        if ($count === 0) {
            return null;
        }

        return [
            'key'        => 'storefront.awaiting_confirmation',
            'severity'   => self::SEVERITY_INFO,
            'count'      => $count,
            'message'    => $count === 1
                ? 'One order is waiting for you to confirm it.'
                : "{$count} orders are waiting for you to confirm them.",
            'detail'     => 'Customers have placed these orders. Stock is not reserved until staff '
                . 'confirm availability.',
            'route'      => null,
            'permission' => 'manage_storefront_orders',
        ];
    }

    /**
     * No usable sales tax profile.
     *
     * Surfaced rather than defaulted, because a silent zero-tax fallback is
     * indistinguishable on a posted invoice from a legitimately exempt sale.
     */
    protected function missingTaxConfiguration(int $organizationId): ?array
    {
        if (! Schema::hasTable('tax_profiles')) {
            return null;
        }

        if (! $this->taxProfiles->salesConfigurationMissing(null, $organizationId)) {
            return null;
        }

        return [
            'key'        => 'finance.tax_not_configured',
            'severity'   => self::SEVERITY_WARNING,
            'count'      => 1,
            'message'    => 'Tax has not been set up yet.',
            'detail'     => 'No sales tax profile is effective for today, so taxable sales cannot be '
                . 'priced correctly. Until this is set up, sales will not carry tax — which is not the '
                . 'same as being exempt.',
            'route'      => null,
            'permission' => 'manage_feature_flags',
        ];
    }

    /* ------------------------------------------------------------------ */

    /**
     * Storefront payments whose money state and internal state disagree
     * (Phase 12, blueprint §41; closes the remaining half of IMP-027).
     *
     * `finance:reconcile-storefront-settlements` has computed this since the
     * prior remediation programme, and it is a good command — it REPORTS rather
     * than acting, because every discrepancy here is a decision with money
     * attached. But it only speaks to a cron log. A treasurer looking at the
     * finance screen had no way to know a payment had been verified at the
     * gateway and never posted, or that an order was marked refunded with no
     * provider record behind it.
     *
     * This surfaces the same conditions where somebody will see them. It
     * deliberately does not fix anything: the command's own docblock explains
     * why automatic correction risks a worse outcome than the discrepancy, and
     * nothing here changes that.
     */
    protected function unsettledStorefrontPayments(int $organizationId): ?array
    {
        if (! Schema::hasTable('storefront_orders')) {
            return null;
        }

        // Paid at the gateway, but the sale never reached finance. The money is
        // real and the books do not show it.
        $verifiedNotPosted = StorefrontOrder::query()
            ->where('organization_id', $organizationId)
            ->where('payment_status', StorefrontOrder::PAY_PAYSTACK_VERIF)
            ->whereIn('status', [
                StorefrontOrder::STATUS_CONFIRMED,
                StorefrontOrder::STATUS_COMPLETED,
            ])
            ->where(function ($q) {
                $q->whereNull('finance_posting_status')
                    ->orWhere('finance_posting_status', '!=', 'posted');
            })
            ->count();

        // Still waiting for the buyer to come back from the gateway, long after
        // any reasonable payment attempt would have finished.
        $stalePending = StorefrontOrder::query()
            ->where('organization_id', $organizationId)
            ->where('status', StorefrontOrder::STATUS_PENDING_PAYMENT)
            ->where('placed_at', '<', now()->subDay())
            ->count();

        $total = $verifiedNotPosted + $stalePending;

        if ($total === 0) {
            return null;
        }

        $messages = [];
        if ($verifiedNotPosted > 0) {
            $messages[] = "{$verifiedNotPosted} paid order(s) have not reached the books";
        }
        if ($stalePending > 0) {
            $messages[] = "{$stalePending} order(s) have been waiting for payment for over a day";
        }

        return [
            'key'      => 'storefront.settlement_discrepancies',
            // Money taken and not recorded is the serious half. A buyer who
            // wandered off mid-payment is untidy, not urgent.
            'severity' => $verifiedNotPosted > 0 ? self::SEVERITY_CRITICAL : self::SEVERITY_INFO,
            'count'    => $total,
            'message'  => ucfirst(implode('; ', $messages)) . '.',
            'detail'   => 'Run `php artisan finance:reconcile-storefront-settlements` for the full list. '
                . 'Do not resubmit anything to the payment provider on the strength of this — '
                . 'a discrepancy can mean the money never moved, and acting blindly risks paying twice.',
            'route'      => null,
            'permission' => 'view_storefront_orders',
        ];
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
