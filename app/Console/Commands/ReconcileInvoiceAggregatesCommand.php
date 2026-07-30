<?php

namespace App\Console\Commands;

use App\Models\Finance\Invoice;
use App\Models\Finance\ReceiptAllocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2c — verify invoice payment-tracking columns match sum of active allocations.
 *
 * The amount_paid + balance_due columns on invoices are denormalised
 * from receipt_allocations. AllocationService keeps them in sync inside
 * a transaction, but bugs, manual DB edits, half-applied migrations,
 * or replication artefacts could cause drift.
 *
 * This command walks all posted, non-cash invoices and compares:
 *   expected_paid    = SUM(active allocations.amount)
 *   actual_paid      = invoice.amount_paid
 *   expected_balance = invoice.total - expected_paid
 *   actual_balance   = invoice.balance_due
 *
 * Reports drift. By default read-only; with --fix, updates the
 * denormalised columns to match SUM(allocations).
 *
 * Usage:
 *   php artisan finance:reconcile-invoice-aggregates             — read only, report drift
 *   php artisan finance:reconcile-invoice-aggregates --fix       — fix drift
 *   php artisan finance:reconcile-invoice-aggregates --org=N     — restrict to one org
 *
 * Schedule: nightly at 02:30 (registered in routes/console.php). Without --fix.
 */
class ReconcileInvoiceAggregatesCommand extends Command
{
    protected $signature = 'finance:reconcile-invoice-aggregates
                            {--fix : Apply fixes (default: read-only report)}
                            {--org= : Restrict to a single organization id}';

    protected $description = 'Reconcile invoice.amount_paid / balance_due against SUM(active allocations).';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $orgId = $this->option('org') ? (int) $this->option('org') : null;

        $this->info("Reconciling invoice payment aggregates " . ($fix ? "[FIX]" : "[REPORT]"));

        $query = Invoice::query()
            ->where('status', Invoice::STATUS_POSTED)
            ->where('is_cash_invoice', false)
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId));

        $total = (clone $query)->count();
        $this->line("Checking {$total} invoices.");

        $drift = [];
        $fixed = 0;

        $query->chunkById(200, function ($invoices) use (&$drift, &$fixed, $fix) {
            $invoiceIds = $invoices->pluck('id')->toArray();

            // One query for all the allocations, group by invoice_id.
            $sums = ReceiptAllocation::query()
                ->whereIn('invoice_id', $invoiceIds)
                ->where('status', ReceiptAllocation::STATUS_ACTIVE)
                ->groupBy('invoice_id')
                ->selectRaw('invoice_id, SUM(amount) as total_paid')
                ->pluck('total_paid', 'invoice_id');

            foreach ($invoices as $inv) {
                $expectedPaid = (string) ($sums[$inv->id] ?? '0');
                $expectedPaid = bcadd($expectedPaid, '0', 2);
                $actualPaid   = (string) $inv->amount_paid;

                $expectedBalance = bcsub((string) $inv->total, $expectedPaid, 2);
                if (bccomp($expectedBalance, '0', 2) < 0) {
                    $expectedBalance = '0';
                }
                $actualBalance = (string) $inv->balance_due;

                $paidDrift    = bccomp($expectedPaid, $actualPaid, 2) !== 0;
                $balanceDrift = bccomp($expectedBalance, $actualBalance, 2) !== 0;

                if ($paidDrift || $balanceDrift) {
                    $drift[] = [
                        'invoice'           => $inv->invoice_number,
                        'id'                => $inv->id,
                        'expected_paid'     => $expectedPaid,
                        'actual_paid'       => $actualPaid,
                        'expected_balance'  => $expectedBalance,
                        'actual_balance'    => $actualBalance,
                    ];

                    if ($fix) {
                        // Recompute fully_paid_at as well.
                        $fullyPaidAt = bccomp($expectedBalance, '0', 2) === 0 && bccomp($expectedPaid, '0', 2) > 0
                            ? ($inv->last_payment_at ?? now())
                            : null;

                        $inv->update([
                            'amount_paid' => $expectedPaid,
                            'balance_due' => $expectedBalance,
                            'fully_paid_at' => $fullyPaidAt,
                        ]);
                        $fixed++;
                    }
                }
            }
        });

        if (empty($drift)) {
            $this->info("No drift detected — all invoices reconcile cleanly.");
            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn("Drift detected on " . count($drift) . " invoices:");
        $this->table(
            ['Invoice', 'ID', 'Expected Paid', 'Actual Paid', 'Expected Balance', 'Actual Balance'],
            array_slice($drift, 0, 25),
        );

        if (count($drift) > 25) {
            $this->line("... and " . (count($drift) - 25) . " more (full list in laravel.log if --fix was applied).");
        }

        if ($fix) {
            $this->info("Fixed {$fixed} invoices.");
            return self::SUCCESS;
        }

        $this->newLine();
        $this->error("Reconciliation FAILED — drift detected. Re-run with --fix to apply, or investigate manually.");
        return self::FAILURE;
    }
}
