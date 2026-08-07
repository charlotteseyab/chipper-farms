<?php

namespace App\Services\Finance\Purchases;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Models\Finance\Account;
use App\Models\Finance\JournalPosting;
use App\Models\Finance\SimplePurchase;
use App\Models\Finance\SimplePurchaseLine;
use App\Models\RawMaterial;
use App\Models\User;
use App\Services\Inventory\ReceiveRawMaterialService;
use App\Services\Numbering\NumberingService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Records a farm purchase (Phase 6b, blueprint §24).
 *
 * THIS SERVICE DOES NOT POST THE INVENTORY LEG
 * ---------------------------------------------
 * That is the single most important thing about it, and it was established by
 * investigation rather than assumption (Phase 6 report §5).
 *
 * `ReceiveRawMaterialService` dispatches `RawMaterialReceived`, and
 * `RawMaterialReceivedSubscriber` posts the inventory journal:
 *
 *     Dr Inventory / Cr GR-IR              (when a purchase request exists)
 *     Dr Inventory / Cr Inventory Suspense (direct receipt, no PR)
 *
 * A purchase workflow that also posted `Dr Inventory / Cr Payables` would debit
 * inventory TWICE for one delivery. Inventory would be overstated by the value
 * of every stock purchase, the subledger-to-control reconciliation would drift
 * by exactly that amount, and the error would propagate into COGS on every
 * subsequent sale. Blueprint SEC-002 in its most literal form.
 *
 * So the division of labour is:
 *
 *     stock line     → ReceiveRawMaterialService  (subledger + inventory GL)
 *     expense line   → this service               (expense GL)
 *     settlement leg → this service               (clears suspense/expense
 *                                                  against cash or payables)
 *
 * The settlement journal is the ONLY journal this class builds. For a stock
 * line it debits Inventory Suspense — the account the subscriber credited —
 * so the clearing account nets to zero once both halves have run. That is the
 * standard GR/IR shape this codebase already uses.
 *
 * PAY-LATER REQUIRES A SUPPLIER
 * -----------------------------
 * Same rule as Quick Expense, for the same reason: a payable is money owed to
 * someone, and a liability with no counterparty cannot be aged or settled.
 */
class RecordSimplePurchaseService
{
    public function __construct(
        protected PostingEngine $postingEngine,
        protected NumberingService $numbering,
        protected ReceiveRawMaterialService $receiver,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * @param array{
     *   purchase_date: string,
     *   supplier_id?: ?int,
     *   supplier_invoice_reference?: ?string,
     *   settlement?: string,
     *   payment_method_key?: ?string,
     *   external_reference?: ?string,
     *   notes?: ?string,
     *   idempotency_key?: ?string,
     *   lines: list<array{
     *     line_kind: string,
     *     description: string,
     *     quantity: string,
     *     unit_cost: string,
     *     raw_material_id?: ?int,
     *     category_key?: ?string,
     *     unit?: ?string,
     *   }>,
     * } $input
     */
    public function record(array $input, User $actor): SimplePurchase
    {
        $organizationId = $this->currentOrganization->get()?->id;

        if ($organizationId === null) {
            throw new \RuntimeException('Cannot record a purchase without an organisation context.');
        }

        $settlement     = $input['settlement'] ?? SimplePurchase::SETTLEMENT_PAID_NOW;
        $idempotencyKey = $input['idempotency_key'] ?? $this->deriveKey($input, $actor);

        $existing = SimplePurchase::query()
            ->forOrganization($organizationId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $lines = $this->validateLines($input['lines'] ?? []);
        $this->assertSettlement($settlement, $input);

        $date = CarbonImmutable::parse($input['purchase_date']);

        return DB::transaction(function () use (
            $input, $actor, $organizationId, $settlement, $idempotencyKey, $date, $lines
        ): SimplePurchase {
            $subtotal = '0.00';
            foreach ($lines as $line) {
                $subtotal = bcadd($subtotal, $line['line_total'], 2);
            }

            $purchase = SimplePurchase::create([
                'organization_id'            => $organizationId,
                'purchase_number'            => $this->numbering->next(
                    sequenceType:   'simple_purchase',
                    prefix:         'PUR',
                    year:           (int) $date->format('Y'),
                    organizationId: $organizationId,
                    padding:        5,
                ),
                'purchase_date'              => $date->toDateString(),
                'supplier_id'                => $input['supplier_id'] ?? null,
                'supplier_invoice_reference' => $input['supplier_invoice_reference'] ?? null,
                'settlement'                 => $settlement,
                'payment_method_key'         => $input['payment_method_key'] ?? null,
                'external_reference'         => $input['external_reference'] ?? null,
                'subtotal'                   => $subtotal,
                'tax_amount'                 => '0.00',
                'total_amount'               => $subtotal,
                'currency_code'              => 'GHS',
                'status'                     => SimplePurchase::STATUS_DRAFT,
                'recorded_by_user_id'        => $actor->id,
                'idempotency_key'            => $idempotencyKey,
                'notes'                      => $input['notes'] ?? null,
            ]);

            $settlementLines = [];

            foreach ($lines as $line) {
                $row = SimplePurchaseLine::create([
                    'organization_id'    => $organizationId,
                    'simple_purchase_id' => $purchase->id,
                    'line_kind'          => $line['line_kind'],
                    'raw_material_id'    => $line['raw_material_id'] ?? null,
                    'category_key'       => $line['category_key'] ?? null,
                    'description'        => $line['description'],
                    'quantity'           => $line['quantity'],
                    'unit'               => $line['unit'] ?? null,
                    'unit_cost'          => $line['unit_cost'],
                    'line_total'         => $line['line_total'],
                ]);

                $settlementLines[] = $line['line_kind'] === SimplePurchaseLine::KIND_STOCK
                    ? $this->receiveStockLine($row, $line, $purchase, $actor, $date, $organizationId)
                    : $this->expenseLine($row, $purchase, $organizationId);
            }

            $creditAccount = $this->resolveCreditAccount($settlement, $input, $organizationId);

            $batch = $this->postingEngine->post(
                $this->buildSettlementDraft(
                    $purchase, $settlementLines, $creditAccount, $date, $subtotal
                ),
                $actor,
            );

            $purchase->update([
                'settlement_batch_id'  => $batch->id,
                'payment_account_code' => $settlement === SimplePurchase::SETTLEMENT_PAID_NOW
                    ? $creditAccount->code : null,
                'status'               => SimplePurchase::STATUS_POSTED,
            ]);

            return $purchase->fresh('lines');
        });
    }

    /* ------------------------------------------------------------------ */

    /**
     * Receive a stock line through the existing service, then return the
     * settlement debit that clears what its subscriber credited.
     *
     * @return array{account_id:int, amount:string, description:string}
     */
    protected function receiveStockLine(
        SimplePurchaseLine $row,
        array $line,
        SimplePurchase $purchase,
        User $actor,
        CarbonImmutable $date,
        int $organizationId,
    ): array {
        // NOTE: `raw_materials` carries no organization_id — it is one of the
        // tables Phase 0 §6 flagged as pre-dating organisation scoping, so the
        // catalogue is shared across tenants. Scoping the lookup by organisation
        // would find nothing at all.
        //
        // Tenancy for the resulting movement is enforced downstream:
        // ReceiveRawMaterialService calls currentOrganization->requireId() and
        // stamps the movement, so a purchase cannot write into another farm's
        // subledger even though it can name a shared material.
        $material = RawMaterial::query()
            ->where('id', $line['raw_material_id'])
            ->first();

        if ($material === null) {
            throw new \InvalidArgumentException(
                'That stock item was not found for this organisation.'
            );
        }

        $result = $this->receiver->execute($material, $actor, [
            'quantity'       => (float) $line['quantity'],
            'unit_cost'      => (float) $line['unit_cost'],
            'supplier_id'    => $purchase->supplier_id,
            'receipt_reason' => 'simple_purchase',
            'occurred_at'    => $date,
            'reference'      => $purchase->purchase_number,
        ]);

        $movement = $result['movement'] ?? null;
        if ($movement !== null) {
            $row->update(['inventory_movement_id' => $movement->id ?? null]);
        }

        // The subscriber credited Inventory Suspense for a direct receipt. The
        // settlement debits it back, so the clearing account nets to zero once
        // both halves have run.
        return [
            'account_id'  => $this->suspenseAccount($organizationId)->id,
            'amount'      => $line['line_total'],
            'description' => 'Clearing receipt: ' . $line['description'],
        ];
    }

    /**
     * @return array{account_id:int, amount:string, description:string}
     */
    protected function expenseLine(
        SimplePurchaseLine $row,
        SimplePurchase $purchase,
        int $organizationId,
    ): array {
        // Reuses the Quick Expense category mapping rather than inventing a
        // second farm-language vocabulary for the same concept.
        $code = config("expense_categories.categories.{$row->category_key}.account")
            ?? config('expense_categories.suspense_account');

        $account = $this->findAccount((string) $code, $organizationId)
            ?? $this->suspenseAccount($organizationId);

        $row->update(['expense_account_code' => $account->code]);

        return [
            'account_id'  => $account->id,
            'amount'      => (string) $row->line_total,
            'description' => $row->description,
        ];
    }

    /**
     * The settlement journal — the only one this workflow owns.
     *
     * @param list<array{account_id:int, amount:string, description:string}> $debits
     */
    protected function buildSettlementDraft(
        SimplePurchase $purchase,
        array $debits,
        Account $creditAccount,
        CarbonImmutable $date,
        string $total,
    ): JournalBatchDraft {
        $lines = [];

        foreach ($debits as $debit) {
            $lines[] = new JournalLineDraft(
                accountId:   $debit['account_id'],
                debit:       $debit['amount'],
                credit:      '0',
                description: $debit['description'],
            );
        }

        $lines[] = new JournalLineDraft(
            accountId:   $creditAccount->id,
            debit:       '0',
            credit:      $total,
            description: 'Purchase ' . $purchase->purchase_number,
        );

        return new JournalBatchDraft(
            batchType:      'auto_simple_purchase',
            postingDate:    $date,
            journals:       [new JournalDraft(
                journalType: 'simple_purchase',
                lines:       $lines,
                reference:   $purchase->purchase_number,
                description: 'Purchase ' . $purchase->purchase_number,
                sourceType:  SimplePurchase::class,
                sourceId:    $purchase->id,
            )],
            description:    'Purchase ' . $purchase->purchase_number,
            sourceModule:   'finance.purchases',
            sourceType:     SimplePurchase::class,
            sourceId:       $purchase->id,
            idempotencyKey: 'simple_purchase:' . $purchase->id,
            postingPurpose: JournalPosting::PURPOSE_CASH_PAYMENT,
        );
    }

    /* ------------------------------------------------------------------ */

    /** @return list<array<string,mixed>> */
    protected function validateLines(array $lines): array
    {
        if ($lines === []) {
            throw new \InvalidArgumentException('A purchase needs at least one line.');
        }

        $out = [];

        foreach ($lines as $i => $line) {
            $kind = $line['line_kind'] ?? SimplePurchaseLine::KIND_EXPENSE;

            if (! in_array($kind, [SimplePurchaseLine::KIND_STOCK, SimplePurchaseLine::KIND_EXPENSE], true)) {
                throw new \InvalidArgumentException("Line {$i}: unknown kind [{$kind}].");
            }

            $quantity = (string) ($line['quantity'] ?? '1');
            $unitCost = (string) ($line['unit_cost'] ?? '0');

            if (! is_numeric($quantity) || bccomp($quantity, '0', 4) <= 0) {
                throw new \InvalidArgumentException("Line {$i}: quantity must be greater than zero.");
            }

            if (! is_numeric($unitCost) || bccomp($unitCost, '0', 4) < 0) {
                throw new \InvalidArgumentException("Line {$i}: unit cost cannot be negative.");
            }

            if ($kind === SimplePurchaseLine::KIND_STOCK && empty($line['raw_material_id'])) {
                throw new \InvalidArgumentException(
                    "Line {$i}: a stock line needs an item, so the delivery can be received into "
                    . 'inventory.'
                );
            }

            $line['line_kind']  = $kind;
            $line['quantity']   = $quantity;
            $line['unit_cost']  = $unitCost;
            $line['line_total'] = bcadd(bcmul($quantity, $unitCost, 8), '0', 2);

            if (bccomp($line['line_total'], '0', 2) <= 0) {
                throw new \InvalidArgumentException("Line {$i}: line total must be greater than zero.");
            }

            $out[] = $line;
        }

        return $out;
    }

    protected function assertSettlement(string $settlement, array $input): void
    {
        if (! in_array($settlement, [
            SimplePurchase::SETTLEMENT_PAID_NOW,
            SimplePurchase::SETTLEMENT_PAY_LATER,
        ], true)) {
            throw new \InvalidArgumentException("Unknown settlement [{$settlement}].");
        }

        if ($settlement === SimplePurchase::SETTLEMENT_PAY_LATER && empty($input['supplier_id'])) {
            throw new \InvalidArgumentException(
                'A pay-later purchase needs a supplier. Money owed to nobody cannot be chased '
                . 'or settled.'
            );
        }

        if ($settlement === SimplePurchase::SETTLEMENT_PAID_NOW) {
            $method = $input['payment_method_key'] ?? null;

            if ($method !== null && config("expense_categories.payment_accounts.{$method}") === null) {
                throw new \InvalidArgumentException("Unknown payment method [{$method}].");
            }
        }
    }

    protected function resolveCreditAccount(string $settlement, array $input, int $organizationId): Account
    {
        $code = $settlement === SimplePurchase::SETTLEMENT_PAY_LATER
            ? (string) config('expense_categories.payable_account')
            : (string) config(
                'expense_categories.payment_accounts.'
                . ($input['payment_method_key'] ?? 'cash') . '.account'
            );

        $account = $this->findAccount($code, $organizationId);

        if ($account === null) {
            throw new \DomainException(
                "Account [{$code}] is missing from the chart of accounts, so this purchase "
                . 'cannot be posted.'
            );
        }

        return $account;
    }

    protected function suspenseAccount(int $organizationId): Account
    {
        $account = $this->findAccount(
            (string) config('expense_categories.suspense_account'),
            $organizationId,
        );

        if ($account === null) {
            throw new \DomainException(
                'The suspense account is missing from the chart of accounts.'
            );
        }

        return $account;
    }

    protected function findAccount(string $code, int $organizationId): ?Account
    {
        // Organisation-scoped wins over a global template of the same code —
        // the same defect corrected in MoneyAccountService (Phase 5 §4.1).
        return Account::query()
            ->where('code', $code)
            ->where(function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId)->orWhereNull('organization_id');
            })
            ->orderByRaw('CASE WHEN organization_id IS NULL THEN 1 ELSE 0 END')
            ->first();
    }

    protected function deriveKey(array $input, User $actor): string
    {
        return 'sp:' . hash('sha256', json_encode([
            $actor->id,
            $input['purchase_date'] ?? '',
            $input['supplier_id'] ?? '',
            $input['supplier_invoice_reference'] ?? '',
            $input['lines'] ?? [],
        ]));
    }
}
