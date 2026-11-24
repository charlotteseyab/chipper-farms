<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Health\HealthProgrammeService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Schedule the doses herd health programmes ask for: animals reaching an age,
 * sows confirmed pregnant or farrowed, herd-wide dates coming round.
 */
class ScheduleHealthProgrammes extends Command
{
    protected $signature = 'health:schedule-programmes {--organization= : Only this organisation id}';

    protected $description = 'Schedule routine vaccinations, deworming and other programme doses for the animals that qualify';

    public function handle(HealthProgrammeService $programmes, CurrentOrganization $current): int
    {
        $now = CarbonImmutable::now();
        $organizations = Organization::query()->when($this->option('organization'), fn ($q, $id) => $q->whereKey($id))->get();
        $previous = $current->get();

        foreach ($organizations as $organization) {
            // Bind each farm in turn so one farm's programme can never pick up
            // another's animals.
            $current->bind($organization);
            try {
                $scheduled = $programmes->scheduleAll($now);
            } finally {
                $current->bind($previous);
            }
            if ($scheduled > 0) {
                $this->info("{$organization->name}: {$scheduled} programme dose(s) scheduled.");
            }
        }

        return self::SUCCESS;
    }
}
