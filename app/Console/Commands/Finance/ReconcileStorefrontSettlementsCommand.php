<?php

namespace App\Console\Commands\Finance;

use App\Models\Storefront\StorefrontOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Detects storefront orders whose money state and internal state disagree
 * (remediation brief §11.7).
 *
 * WHY THIS EXISTS RATHER THAN AN AUTOMATED FIX
 * --------------------------------------------
 * Every condition below is a discrepancy between what the business believes and
 * what actually happened. Several of them CANNOT be corrected automatically
 * without risking a worse outcome:
 *
 *   - a refund that settled at Paystack but was never completed internally
 *     cannot be "completed" by flipping a status; the accounting reversal has to
 *     happen and, for pig sales, that reversal does not yet exist (CF-P0-008);
 *   - an internally-refunded order with no provider success may mean the money
 *     never moved, and re-submitting blindly could refund twice.
 *
 * So this command REPORTS. Acting on a discrepancy is a decision with money
 * attached, and the failure mode this whole programme keeps finding is software
 * that silently decided on the operator's behalf — the COD settlement note that
 * nothing acted on, the refund status set without contacting the gateway.
 *
 * Exit codes: 0 = clean, 1 = discrepancies found. Suitable for cron with
 * alerting on non-zero.
 *
 * Usage:
 *   php artisan finance:reconcile-storefront-settlements
 *   php artisan finance:reconcile-storefront-settlements --json
 */
class ReconcileStorefrontSettlementsCommand extends Command
{
    protected $signature = 'finance:reconcile-storefront-settlements
                            {--json : Emit machine-readable output for monitoring}
                            {--stale-hours=24 : Age after which a submitted refund is considered stuck}';

    protected $description = 'Report storefront orders whose payment, refund or finance state is inconsistent';

    public function handle(): int
    {
        $staleHours = max(1, (int) $this->option('stale-hours'));

        $findings = [
            'refund_submitted_never_settled' => $this->refundsSubmittedButNeverSettled($staleHours),
            'refunded_without_provider_proof' => $this->refundedWithoutProviderProof(),
            'refund_submission_failed'        => $this->refundSubmissionFailures(),
            'paid_without_finance_posting'    => $this->paidWithoutFinancePosting(),
            'refunded_with_incomplete_reversal' => $this->refundedWithIncompleteReversal(),
            'completed_cod_without_settled_sale' => $this->completedCodWithoutSettledSale(),
        ];

        $total = array_sum(array_map('count', $findings));

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'checked_at'  => now()->toIso8601String(),
                'total'       => $total,
                'findings'    => $findings,
            ], JSON_PRETTY_PRINT));

            return $total === 0 ? self::SUCCESS : self::FAILURE;
        }

        $this->renderHuman($findings, $total, $staleHours);

        return $total === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * A refund was accepted by Paystack but never reached settlement.
     *
     * Paystack settles asynchronously, so refund_pending is normal for a while.
     * Beyond the stale window it means the refund.processed webhook never
     * arrived — which is expected today, because that webhook is NOT implemented
     * (CF-P0-007). Until it is, this check is the only thing that surfaces a
     * refund the customer is still waiting on.
     *
     * @return list<array<string,mixed>>
     */
    protected function refundsSubmittedButNeverSettled(int $staleHours): array
    {
        return StorefrontOrder::query()
            ->whereNotNull('refund_submitted_at')
            ->whereNull('refunded_at')
            ->where('refund_submitted_at', '<=', now()->subHours($staleHours))
            ->get()
            ->map(fn (StorefrontOrder $o) => [
                'order'           => $o->reference,
                'submitted_at'    => optional($o->refund_submitted_at)->toIso8601String(),
                'provider_status' => $o->refund_provider_status,
                'provider_ref'    => $o->refund_provider_reference,
                'note'            => 'Refund accepted by the provider but never settled. The customer is still waiting.',
            ])
            ->all();
    }

    /**
     * The order claims to be refunded but carries no evidence that money moved.
     *
     * This is the CF-P0-007 signature. Any order in this state that predates the
     * remediation was marked refunded WITHOUT the gateway ever being contacted,
     * so the funds may still be with the merchant.
     *
     * Cash-on-delivery is excluded: those have no gateway leg by definition, and
     * flagging every COD refund would bury the real cases.
     *
     * @return list<array<string,mixed>>
     */
    protected function refundedWithoutProviderProof(): array
    {
        return StorefrontOrder::query()
            ->where('status', StorefrontOrder::STATUS_REFUNDED)
            ->where('payment_method', '!=', StorefrontOrder::METHOD_COD)
            ->whereNull('refund_provider_reference')
            ->get()
            ->map(fn (StorefrontOrder $o) => [
                'order'          => $o->reference,
                'refunded_at'    => optional($o->refunded_at)->toIso8601String(),
                'payment_method' => $o->payment_method,
                'note'           => 'Marked refunded with no provider reference. Verify against the '
                                    . 'Paystack dashboard before assuming the customer was paid.',
            ])
            ->all();
    }

    /** @return list<array<string,mixed>> */
    protected function refundSubmissionFailures(): array
    {
        return StorefrontOrder::query()
            ->where('refund_provider_status', 'submission_failed')
            ->get()
            ->map(fn (StorefrontOrder $o) => [
                'order'  => $o->reference,
                'reason' => $o->refund_failure_reason,
                'note'   => 'Refund submission failed; no money moved. Safe to retry — the stored '
                            . 'idempotency key prevents a double refund.',
            ])
            ->all();
    }

    /**
     * Money was taken but nothing was posted to finance.
     *
     * @return list<array<string,mixed>>
     */
    protected function paidWithoutFinancePosting(): array
    {
        return StorefrontOrder::query()
            ->where('payment_status', StorefrontOrder::PAY_PAID)
            ->where(function ($q) {
                $q->whereNull('finance_posting_status')
                  ->orWhere('finance_posting_status', '!=', 'posted');
            })
            ->get()
            ->map(fn (StorefrontOrder $o) => [
                'order'          => $o->reference,
                'posting_status' => $o->finance_posting_status ?? 'null',
                'note'           => 'Paid but not posted to finance. Check the outbox backlog first — '
                                    . 'posting is asynchronous and this may simply be lagging.',
            ])
            ->all();
    }

    /**
     * Refunded orders whose reversal was handed to finance as a note (CF-P0-008).
     *
     * This is the open finding made visible. The refund service reverses the
     * revenue and receipt journals but hands COGS, inventory, biological-asset
     * and pig-restore work to finance through finance_reversal_note — and
     * nothing consumes that note. Every order listed here has real accounting
     * still outstanding.
     *
     * @return list<array<string,mixed>>
     */
    protected function refundedWithIncompleteReversal(): array
    {
        return StorefrontOrder::query()
            ->whereIn('status', [
                StorefrontOrder::STATUS_REFUNDED,
                StorefrontOrder::STATUS_REFUND_APPROVED,
            ])
            ->whereNotNull('finance_reversal_note')
            ->where('finance_reversal_note', 'like', '%Handed to finance%')
            ->get()
            ->map(fn (StorefrontOrder $o) => [
                'order'   => $o->reference,
                'handoff' => $o->finance_reversal_note,
                'note'    => 'Sales-return accounting outstanding (CF-P0-008): COGS, inventory, '
                             . 'biological assets and/or pig restore were NOT reversed automatically.',
            ])
            ->all();
    }

    /**
     * Completed COD orders with no settled sale behind them.
     *
     * Completion now refuses when settlement fails (CF-P0-009), so new orders
     * cannot enter this state. Orders completed BEFORE that fix can still be
     * here, each representing cash the business believes it collected with no
     * receipt to show for it.
     *
     * @return list<array<string,mixed>>
     */
    protected function completedCodWithoutSettledSale(): array
    {
        $orders = StorefrontOrder::query()
            ->where('status', StorefrontOrder::STATUS_COMPLETED)
            ->where('payment_method', StorefrontOrder::METHOD_COD)
            ->where('payment_status', StorefrontOrder::PAY_PAID)
            ->with('items')
            ->get();

        $findings = [];

        foreach ($orders as $order) {
            $unlinked = $order->items->filter(
                fn ($item) => $item->posted_sale_id === null || $item->posted_sale_type === null,
            );

            if ($unlinked->isEmpty()) {
                continue;
            }

            $findings[] = [
                'order'          => $order->reference,
                'unlinked_lines' => $unlinked->count(),
                'note'           => 'Completed and marked paid with unlinked lines — cash recognised '
                                    . 'with no sale, receipt or receivable behind it.',
            ];
        }

        return $findings;
    }

    /**
     * @param  array<string, list<array<string,mixed>>>  $findings
     */
    protected function renderHuman(array $findings, int $total, int $staleHours): void
    {
        $this->newLine();
        $this->info('Storefront settlement reconciliation');
        $this->line('Stale-refund threshold: ' . $staleHours . 'h');
        $this->newLine();

        if ($total === 0) {
            $this->info('No discrepancies found.');

            return;
        }

        foreach ($findings as $key => $rows) {
            if ($rows === []) {
                continue;
            }

            $this->warn(str_replace('_', ' ', strtoupper($key)) . ' — ' . count($rows));

            foreach ($rows as $row) {
                $this->line('  • ' . ($row['order'] ?? '?') . ': ' . ($row['note'] ?? ''));
            }

            $this->newLine();
        }

        $this->error("{$total} discrepancy(ies) found. These need a human decision — none is "
            . 'corrected automatically, because every one has money attached.');
    }
}
