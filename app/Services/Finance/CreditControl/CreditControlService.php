<?php

namespace App\Services\Finance\CreditControl;

use App\Models\Counterparty;
use App\Services\Finance\Allocations\CustomerLedgerService;

/**
 * Credit control: evaluates whether a customer is over their credit
 * limit, and decides whether to warn or block based on their hold mode.
 *
 * Decision 24: configurable per customer.
 *   credit_hold_mode = 'none'  → always allow, no warnings
 *   credit_hold_mode = 'warn'  → over-limit produces warning; sale proceeds
 *   credit_hold_mode = 'block' → over-limit hard-blocks; admin override only
 *
 * The check answers two questions for the operations UI / pig sale flow:
 *   1. Should we show a warning to the user?
 *   2. Should we hard-block the action?
 *
 * Operations decides where to invoke this. The pig sale form, the storefront
 * checkout, and any future "create order" flow all call CheckResult::isBlocked()
 * to decide whether to refuse the action.
 *
 * Phase 2d ships the service + the schema. The actual enforcement in
 * the pig sale form is gated by config('finance.credit_control.enforce_in_pig_sales')
 * which defaults to false. Operations turns it on per-org when ready.
 */
class CreditControlService
{
    public function __construct(
        protected CustomerLedgerService $ledgerService,
    ) {
    }

    /**
     * Evaluate a customer's credit standing for a proposed new transaction.
     *
     * @param  Counterparty  $customer
     * @param  string        $newTransactionAmount  decimal-as-string, the amount
     *                       about to be added to the customer's outstanding (e.g.
     *                       a pig sale's expected billed amount).
     */
    public function evaluate(Counterparty $customer, string $newTransactionAmount = '0'): CheckResult
    {
        // Customers with no credit limit set: no checking. Operations may
        // require a credit limit on every customer in Phase 6+; for now
        // it's optional.
        $limit = $customer->credit_limit;
        if ($limit === null || bccomp((string) $limit, '0', 2) <= 0) {
            return new CheckResult(
                customer: $customer,
                outstandingBalance: $this->ledgerService->outstandingBalance($customer),
                projectedBalance:   $this->projected($customer, $newTransactionAmount),
                limit: null,
                holdMode: $customer->credit_hold_mode ?? 'warn',
                isOverLimit: false,
                message: null,
            );
        }

        $outstanding = $this->ledgerService->outstandingBalance($customer);
        $projected = bcadd($outstanding, $newTransactionAmount, 2);

        $isOverLimit = bccomp($projected, (string) $limit, 2) > 0;

        $message = null;
        if ($isOverLimit) {
            $excess = bcsub($projected, (string) $limit, 2);
            $message = sprintf(
                'Customer %s would be %s %s over their credit limit (%s) after this transaction. ' .
                'Outstanding: %s; new transaction: %s; projected: %s.',
                $customer->name,
                $customer->currency_code ?? 'GHS',
                number_format((float) $excess, 2),
                number_format((float) $limit, 2),
                number_format((float) $outstanding, 2),
                number_format((float) $newTransactionAmount, 2),
                number_format((float) $projected, 2),
            );
        }

        return new CheckResult(
            customer:            $customer,
            outstandingBalance:  $outstanding,
            projectedBalance:    $projected,
            limit:               (string) $limit,
            holdMode:            $customer->credit_hold_mode ?? 'warn',
            isOverLimit:         $isOverLimit,
            message:             $message,
        );
    }

    protected function projected(Counterparty $customer, string $newTransactionAmount): string
    {
        $outstanding = $this->ledgerService->outstandingBalance($customer);
        return bcadd($outstanding, $newTransactionAmount, 2);
    }
}
