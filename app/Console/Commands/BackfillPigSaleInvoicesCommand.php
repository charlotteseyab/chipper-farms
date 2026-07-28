<?php

namespace App\Console\Commands;

use App\Models\Counterparty;
use App\Models\Finance\Invoice;
use App\Models\Finance\InvoiceLine;
use App\Models\Finance\JournalPosting;
use App\Models\PaymentMethod;
use App\Models\PigSale;
use App\Models\User;
use App\Services\Finance\Allocations\AllocationService;
use App\Services\Finance\Receipts\ReceiptService;
use App\Services\Numbering\NumberingService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 2c — backfill historical pig sales as invoices.
 *
 * Strategy (Decision 18, Option B in the README):
 *   - Walk pig_sales WHERE invoice_id IS NULL AND is_posted = true
 *   - For each, create an invoice with one line
 *   - Set the invoice status to 'posted'
 *   - LINK to the EXISTING journal_batch_id from the legacy posting
 *     (lookup via journal_postings idempotency key)
 *   - DO NOT create new GL postings (the journals already exist)
 *   - Update pig_sales.invoice_id with the new invoice id
 *
 *   For sales with payment_status = 'paid':
 *     - Synthesise a backfilled Receipt (is_backfilled=true, no new GL)
 *     - Allocate the full balance against the new invoice
 *     - Mark invoice fully_paid_at = sale's payment_status_updated_at
 *
 *   For sales with payment_status = 'partial':
 *     - Create the invoice but skip receipt synthesis (legacy schema doesn't
 *       record the partial amount)
 *     - Log a warning so operations can manually reconcile
 *
 *   For sales with payment_status = 'pending':
 *     - Create invoice only; remains open in AR
 *
 * Idempotency:
 *   The command is idempotent. Re-running it skips sales that already have
 *   invoice_id set. Safe to run partially, abort, and resume.
 *
 *   Safety rails:
 *     --dry-run         No DB writes; reports counts only
 *     --org=N           Process only one org's records
 *     --since=YYYY-MM-DD  Process only sales on/after a date (incremental run)
 *     --limit=N         Process only N records
 *     --user=N          User id to attribute the backfill to (default: first superuser)
 *
 *   Concurrency:
 *     Wrap each pig_sale's transformation in DB::transaction(). Failure on
 *     one sale doesn't roll back others. Errors are logged and reported.
 *
 * Verification after run:
 *   1. SELECT COUNT(*) FROM pig_sales WHERE invoice_id IS NOT NULL  -- should match expected
 *   2. SELECT COUNT(*) FROM invoices WHERE source_type='App\Models\PigSale'
 *   3. Trial balance unchanged (no new GL postings)
 *   4. Customer ledger now shows historical invoices for each customer
 */
class BackfillPigSaleInvoicesCommand extends Command
{
    protected $signature = 'finance:backfill-pig-sale-invoices
                            {--dry-run : Report only, no DB writes}
                            {--org= : Restrict to a single organization id}
                            {--since= : Process only sales on or after this date (YYYY-MM-DD)}
                            {--limit= : Process at most this many records}
                            {--user= : User id to attribute backfill audit to (default: first superuser)}';

    protected $description = 'Materialise historical pig sales as invoices + receipts (no new GL postings).';

    public function __construct(
        protected NumberingService $numbering,
        protected ReceiptService $receiptService,
        protected AllocationService $allocationService,
        protected CurrentOrganization $currentOrganization,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $orgId  = $this->option('org') ? (int) $this->option('org') : null;
        $since  = $this->option('since') ? CarbonImmutable::parse($this->option('since')) : null;
        $limit  = $this->option('limit') ? (int) $this->option('limit') : null;

        $userId = $this->option('user') ? (int) $this->option('user') : $this->resolveDefaultUserId();
        $user = $userId ? User::find($userId) : null;
        if (! $user) {
            $this->error("No user found to attribute backfill (provide --user=ID)");
            return self::FAILURE;
        }

        $this->info("Backfill pig sales → invoices " . ($dryRun ? "[DRY RUN]" : "[LIVE]"));
        $this->line("  attributed to user: {$user->name} (#{$user->id})");
        if ($orgId)  $this->line("  org filter: #{$orgId}");
        if ($since)  $this->line("  since: " . $since->toDateString());
        if ($limit)  $this->line("  limit: {$limit}");
        $this->newLine();

        $query = PigSale::query()
            ->whereNull('invoice_id')
            ->where('is_posted', true)
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->when($since, fn ($q) => $q->whereDate('sale_date', '>=', $since->toDateString()))
            ->orderBy('id');

        $totalToProcess = (clone $query)->count();
        $this->line("Found {$totalToProcess} pig sales to consider.");

        if ($totalToProcess === 0) {
            $this->info("Nothing to do.");
            return self::SUCCESS;
        }

        $stats = [
            'invoices_created'    => 0,
            'invoices_skipped'    => 0,
            'receipts_created'    => 0,
            'partial_skipped'     => 0,
            'pending'             => 0,
            'no_journal_found'    => 0,
            'failed'              => 0,
        ];
        $warnings = [];
        $failures = [];

        $processed = 0;
        $query->chunkById(100, function ($sales) use (&$stats, &$processed, &$warnings, &$failures, $dryRun, $user, $limit) {
            foreach ($sales as $sale) {
                if ($limit && $processed >= $limit) break;
                $processed++;

                try {
                    $result = $dryRun
                        ? $this->simulate($sale)
                        : $this->processOne($sale, $user);

                    $stats[$result] = ($stats[$result] ?? 0) + 1;

                    if ($result === 'partial_skipped') {
                        $warnings[] = "Sale #{$sale->id}: partial payment — manual receipt entry required";
                    }
                    if ($result === 'no_journal_found') {
                        $warnings[] = "Sale #{$sale->id}: marked is_posted=true but no journal_postings row found — invoice created without journal_batch_id";
                    }
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $failures[] = "Sale #{$sale->id}: " . $e->getMessage();
                    Log::error("Backfill failed for sale #{$sale->id}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            return ! ($limit && $processed >= $limit);
        });

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->toArray(),
        );

        if (! empty($warnings)) {
            $this->newLine();
            $this->warn("Warnings (" . count($warnings) . "):");
            foreach (array_slice($warnings, 0, 50) as $w) {
                $this->line("  • {$w}");
            }
            if (count($warnings) > 50) {
                $this->line("  ... and " . (count($warnings) - 50) . " more.");
            }
        }

        if (! empty($failures)) {
            $this->newLine();
            $this->error("Failures (" . count($failures) . "):");
            foreach (array_slice($failures, 0, 20) as $f) {
                $this->line("  No {$f}");
            }
            if (count($failures) > 20) {
                $this->line("  ... and " . (count($failures) - 20) . " more (see laravel.log)");
            }
            return self::FAILURE;
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("Dry run complete. Re-run without --dry-run to apply.");
        } else {
            $this->info("Backfill complete.");
        }

        return self::SUCCESS;
    }

    /**
     * Live processing — wraps in DB::transaction so partial failures don't pollute.
     *
     * Returns one of: 'invoices_created', 'invoices_skipped', 'receipts_created',
     * 'partial_skipped', 'pending', 'no_journal_found'
     */
    protected function processOne(PigSale $sale, User $user): string
    {
        return DB::transaction(function () use ($sale, $user) {
            // Re-read inside transaction to handle concurrent runs.
            $sale = PigSale::lockForUpdate()->findOrFail($sale->id);
            if ($sale->invoice_id) {
                return 'invoices_skipped';
            }

            // Bind the org context for NumberingService.
            $org = $sale->organization_id
                ? \App\Models\Organization::find($sale->organization_id)
                : null;
            if ($org) {
                $this->currentOrganization->bind($org);
            }

            $customer = Counterparty::find($sale->customer_id);
            if (! $customer) {
                throw new \RuntimeException("Sale #{$sale->id} has missing customer #{$sale->customer_id}");
            }

            $existingJournalBatchId = $this->findExistingJournalBatchId($sale);
            $resultMarker = 'invoices_created';
            if (! $existingJournalBatchId) {
                $resultMarker = 'no_journal_found';
            }

            $invoice = $this->createInvoiceForSale($sale, $customer, $existingJournalBatchId, $user);

            $sale->update(['invoice_id' => $invoice->id]);

            $secondaryResult = $this->backfillPaymentTracking($sale, $invoice, $customer, $user);

            return $secondaryResult ?? $resultMarker;
        });
    }

    /**
     * Simulate processing — read-only, returns the same marker as processOne would.
     */
    protected function simulate(PigSale $sale): string
    {
        if ($sale->invoice_id) {
            return 'invoices_skipped';
        }
        $existingJournalBatchId = $this->findExistingJournalBatchId($sale);

        if (! $existingJournalBatchId) {
            return 'no_journal_found';
        }

        return match ($sale->payment_status) {
            'paid'    => 'receipts_created',
            'partial' => 'partial_skipped',
            default   => 'pending',
        };
    }

    /**
     * Look up the existing journal_batch_id from the legacy Phase 1c posting.
     * The posting engine writes journal_postings rows keyed by
     *   {event_type}:{source_type_short}:{source_id}:{posting_purpose}
     * Returns the batch id from that row, or null if none found (rare —
     * indicates is_posted=true was set without a corresponding GL row, which
     * would be a data inconsistency from somewhere else).
     */
    protected function findExistingJournalBatchId(PigSale $sale): ?int
    {
        $idempotencyKey = JournalPosting::buildIdempotencyKey(
            'pig_sale.recorded',
            PigSale::class,
            $sale->id,
            JournalPosting::PURPOSE_REVENUE,
        );

        return JournalPosting::query()
            ->where('idempotency_key', $idempotencyKey)
            ->where('posting_purpose', JournalPosting::PURPOSE_REVENUE)
            ->orderBy('id')
            ->value('journal_batch_id');
    }

    protected function createInvoiceForSale(
        PigSale $sale,
        Counterparty $customer,
        ?int $existingJournalBatchId,
        User $user,
    ): Invoice {
        $invoiceNumber = $this->numbering->next(
            sequenceType:   'invoice',
            prefix:         'INV',
            year:           (int) $sale->sale_date->format('Y'),
            organizationId: $sale->organization_id,
        );

        $isCash = $sale->payment_method_id
            && PaymentMethod::query()
                ->whereKey($sale->payment_method_id)
                ->where(function ($q) {
                    $q->where('is_cash', true)
                      ->orWhere('is_bank', true)
                      ->orWhere('is_mobile_money', true);
                })
                ->exists();

        $total = (string) (float) $sale->sale_price;

        $invoice = Invoice::create([
            'organization_id'           => $sale->organization_id,
            'invoice_number'            => $invoiceNumber,
            'invoice_date'              => $sale->sale_date,
            'due_date'                  => $sale->sale_date,
            'counterparty_id'           => $customer->id,
            'currency_code'             => $customer->currency_code ?? 'GHS',
            'exchange_rate'             => '1',
            'source_type'               => PigSale::class,
            'source_id'                 => $sale->id,
            'status'                    => Invoice::STATUS_POSTED,
            'journal_batch_id'          => $existingJournalBatchId,
            'is_cash_invoice'           => $isCash,
            'payment_method_id'         => $sale->payment_method_id,
            'subtotal'                  => $total,
            'discount_total'            => '0',
            'tax_total'                 => '0',
            'total'                     => $total,
            'amount_paid'               => '0',
            'balance_due'               => $isCash ? '0' : $total,
            'fully_paid_at'             => $isCash ? $sale->updated_at : null,
            'created_by_user_id'        => $user->id,
            'posted_by_user_id'         => $user->id,
            'posted_at'                 => $sale->updated_at,
            'description'               => sprintf(
                'Backfilled invoice for pig sale #%d (%s)',
                $sale->id,
                $sale->document_number ?? '—'
            ),
            'reference'                 => $sale->document_number,
            'internal_notes'            => 'Backfilled by finance:backfill-pig-sale-invoices',
        ]);

        // Create a single invoice line.
        InvoiceLine::create([
            'organization_id'        => $sale->organization_id,
            'invoice_id'             => $invoice->id,
            'line_number'            => 1,
            'description'            => sprintf(
                'Pig sale — %s kg @ %s/kg (%s)',
                number_format((float) $sale->sale_weight, 2),
                number_format((float) $sale->price_per_kg, 2),
                $sale->weight_type ?? 'live',
            ),
            'pig_id'                 => $sale->pig_id,
            'quantity'               => (string) (float) $sale->sale_weight,
            'unit_of_measure'        => 'kg',
            'unit_price'             => (string) (float) $sale->price_per_kg,
            'line_subtotal'          => $total,
            'line_discount_percent'  => '0',
            'line_discount_amount'   => '0',
            'line_tax_amount'        => '0',
            'line_total'             => $total,
        ]);

        return $invoice->fresh();
    }

    /**
     * For sales already paid, synthesise a backfilled receipt + allocation.
     * For partial sales, log only.
     * For pending, no extra action.
     *
     * Returns a marker if it overrides the default 'invoices_created'.
     */
    protected function backfillPaymentTracking(
        PigSale $sale,
        Invoice $invoice,
        Counterparty $customer,
        User $user,
    ): ?string {
        if ($invoice->is_cash_invoice) {
            // Cash invoice — already marked fully_paid_at at creation. No receipt needed
            // (the legacy entry already debited Cash, not AR — there's nothing in AR
            // to allocate against).
            return 'receipts_created';
        }

        switch ($sale->payment_status) {
            case 'paid':
                $receipt = $this->receiptService->createBackfilled(
                    customer: $customer,
                    paymentMethod: $sale->payment_method_id ? PaymentMethod::find($sale->payment_method_id) : null,
                    amount: (string) $invoice->total,
                    receiptDate: CarbonImmutable::instance($sale->payment_status_updated_at ?? $sale->sale_date),
                    createdBy: $user,
                    extras: [
                        'currency_code'      => $invoice->currency_code,
                        'description'        => "Backfilled receipt for paid pig sale #{$sale->id}",
                        'external_reference' => "pig_sale:{$sale->id}",
                    ],
                );

                $this->allocationService->allocate(
                    receipt: $receipt,
                    invoice: $invoice,
                    amount:  (string) $invoice->total,
                    user:    $user,
                    allocationDate: CarbonImmutable::instance($sale->payment_status_updated_at ?? $sale->sale_date),
                    notes: 'Backfilled allocation',
                );

                return 'receipts_created';

            case 'partial':
                // Cannot backfill — legacy schema lost the partial amount.
                return 'partial_skipped';

            default:
                return 'pending';
        }
    }

    protected function resolveDefaultUserId(): ?int
    {
        // Prefer first superuser; fall back to first admin.
        $userId = User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'superuser'))
            ->orderBy('id')
            ->value('id');

        if (! $userId) {
            $userId = User::query()
                ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
                ->orderBy('id')
                ->value('id');
        }

        return $userId;
    }
}
