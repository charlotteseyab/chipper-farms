<?php

namespace App\Services\Finance\Loans;

use App\Models\Finance\Loan;
use App\Models\Finance\LoanScheduleLine;
use App\Services\Finance\Loans\Concerns\HandlesLoanDecimals;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Current vs long-term principal classification report (IAS 1).
 *
 * Principal scheduled to be repaid within 12 months of the reporting date is
 * "current"; the remainder is "non-current". We post all principal to the
 * current account on disbursement (per the locked decision), so this report
 * shows the reclassification an accountant would make at reporting date —
 * how much of each loan's outstanding principal SHOULD sit in the long-term
 * account.
 *
 * Read-only. The deploy guide explains how to post the manual reclass
 * journal (Dr current principal / Cr long-term principal) if desired.
 */
class LoanReclassificationService
{
    use HandlesLoanDecimals;

    public function __construct(protected CurrentOrganization $currentOrganization)
    {
    }

    /**
     * @return array{
     *   as_of:string,
     *   lines: array<int, array<string, mixed>>,
     *   totals: array{current:string, non_current:string, outstanding:string}
     * }
     */
    public function generate(?CarbonInterface $asOf = null): array
    {
        $asOf = $asOf ? CarbonImmutable::parse($asOf->toDateString()) : CarbonImmutable::now();
        $horizon = $asOf->addYear();
        $organizationId = $this->currentOrganization->id();

        $loans = Loan::query()
            ->when($organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'))
            ->where('status', Loan::STATUS_ACTIVE)
            ->get();

        $lines = [];
        $totCurrent = '0';
        $totNonCurrent = '0';

        foreach ($loans as $loan) {
            // Sum unpaid principal due within the next 12 months.
            $scheduleLines = LoanScheduleLine::query()
                ->where('loan_id', $loan->id)
                ->where('status', '!=', LoanScheduleLine::STATUS_PAID)
                ->get();

            $currentPortion = '0';
            foreach ($scheduleLines as $sl) {
                $remainingPrincipal = $this->sub((string) $sl->principal_due, (string) $sl->principal_paid);
                if ($this->cmp($remainingPrincipal, '0') <= 0) {
                    continue;
                }
                $due = CarbonImmutable::parse($sl->due_date->toDateString());
                if ($due->lessThanOrEqualTo($horizon) && $due->greaterThan($asOf->subDay())) {
                    $currentPortion = $this->add($currentPortion, $remainingPrincipal);
                }
            }

            $outstanding = (string) $loan->principal_outstanding;
            // Cap current portion at outstanding (rounding safety).
            if ($this->cmp($currentPortion, $outstanding) > 0) {
                $currentPortion = $outstanding;
            }
            $nonCurrent = $this->sub($outstanding, $currentPortion);

            $lines[] = [
                'loan_id'            => $loan->id,
                'reference'          => $loan->reference,
                'lender_name'        => $loan->lender_name,
                'loan_type'          => $loan->loan_type,
                'outstanding'        => $outstanding,
                'current_portion'    => $currentPortion,
                'non_current_portion'=> $nonCurrent,
            ];

            $totCurrent = $this->add($totCurrent, $currentPortion);
            $totNonCurrent = $this->add($totNonCurrent, $nonCurrent);
        }

        return [
            'as_of' => $asOf->toDateString(),
            'lines' => $lines,
            'totals' => [
                'current'     => $totCurrent,
                'non_current' => $totNonCurrent,
                'outstanding' => $this->add($totCurrent, $totNonCurrent),
            ],
        ];
    }
}
