<?php

namespace App\Console\Commands\Finance;

use App\Models\Finance\Loan;
use App\Models\Finance\LoanScheduleLine;
use App\Models\User;
use App\Services\Finance\Loans\LoanService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Accrue loan interest for all active loans whose schedule lines are due on
 * or before the run date and not yet accrued.
 *
 * Posts Dr interest expense / Cr accrued interest per line, idempotent per
 * (loan, period). Run monthly (or daily — only un-accrued, now-due lines are
 * picked up).
 *
 *   php artisan finance:accrue-loan-interest [--date=YYYY-MM-DD] [--actor=ID] [--dry-run]
 */
class AccrueLoanInterestCommand extends Command
{
    protected $signature = 'finance:accrue-loan-interest
                            {--date= : Accrue lines due on/before this date (default today)}
                            {--actor= : User id to attribute postings to}
                            {--dry-run : List lines that would accrue without posting}';

    protected $description = 'Accrue interest for active loans up to a date (Dr interest expense / Cr accrued interest).';

    public function handle(LoanService $loans, CurrentOrganization $currentOrg): int
    {
        $asOf = $this->option('date') ? CarbonImmutable::parse($this->option('date')) : CarbonImmutable::now();
        $dryRun = (bool) $this->option('dry-run');
        $actor = $this->resolveActor();

        if (! $actor && ! $dryRun) {
            $this->error('No actor user found. Pass --actor=<user_id>.');
            return self::FAILURE;
        }

        $organizationId = $currentOrg->id();

        $activeLoans = Loan::query()
            ->when($organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'))
            ->where('status', Loan::STATUS_ACTIVE)
            ->get();

        $planned = 0;
        $posted = 0;

        foreach ($activeLoans as $loan) {
            $dueLines = LoanScheduleLine::query()
                ->where('loan_id', $loan->id)
                ->where('interest_accrued', false)
                ->whereDate('due_date', '<=', $asOf->toDateString())
                ->where('interest_due', '>', 0)
                ->orderBy('period_number')
                ->get();

            foreach ($dueLines as $line) {
                $planned++;
                $this->line(sprintf('[%s] period %d due %s : interest %s',
                    $loan->reference, $line->period_number, $line->due_date->toDateString(),
                    number_format((float) $line->interest_due, 2)));

                if ($dryRun) {
                    continue;
                }

                $txn = $loans->accrueLine($loan, $line, $actor, CarbonImmutable::parse($line->due_date->toDateString()));
                if ($txn) {
                    $posted++;
                }
            }
        }

        $this->info($dryRun
            ? "Dry run: {$planned} interest accrual(s) would post."
            : "Accrued {$posted} of {$planned} due interest line(s).");

        return self::SUCCESS;
    }

    protected function resolveActor(): ?User
    {
        if ($id = $this->option('actor')) {
            return User::find((int) $id);
        }
        return User::query()->where('role', 'admin')->orWhere('role', 'superuser')->first()
            ?? User::query()->first();
    }
}
