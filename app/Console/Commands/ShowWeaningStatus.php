<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PigManagement;
use App\Models\Weaning;

class ShowWeaningStatus extends Command
{
    protected $signature = 'weaning:show-status';
    protected $description = 'Show current weaning status of all pigs';

    public function handle()
    {
        $this->info('Current Weaning Status of All Pigs');
        $this->info('=====================================');

        $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);
        $maturityAge = config('breeding.cycle.birth_to_maturity_days', 180);

        // Get all pigs with their weaning status
        $allPigs = PigManagement::whereNotNull('date_of_birth')
            ->with(['weaning'])
            ->orderBy('date_of_birth', 'desc')
            ->get();

        $pending = 0;
        $readyForWeaning = 0;
        $overdue = 0;
        $inProgress = 0;
        $completed = 0;

        foreach ($allPigs as $pig) {
            $age = $pig->date_of_birth->diffInDays(now());
            $hasWeaning = $pig->weaning !== null;
            $isCompleted = $hasWeaning && $pig->weaning->maturity_completed;

            if (!$hasWeaning) {
                if ($age < $weaningAge) {
                    $status = 'PENDING';
                    $pending++;
                } elseif ($age < ($weaningAge + 7)) {
                    $status = 'READY FOR WEANING';
                    $readyForWeaning++;
                } else {
                    $status = 'OVERDUE';
                    $overdue++;
                }
            } else {
                if ($isCompleted) {
                    $status = 'COMPLETED';
                    $completed++;
                } else {
                    $status = 'IN PROGRESS';
                    $inProgress++;
                }
            }

            $this->line(sprintf(
                '%-10s | %3d days | %-15s | %s',
                $pig->code,
                $age,
                $status,
                $hasWeaning ? 'Weaned: ' . $pig->weaning->weaning_date->format('Y-m-d') : 'Not weaned'
            ));
        }

        $this->info('');
        $this->info('Summary:');
        $this->info("Pending: {$pending}");
        $this->info("Ready for Weaning: {$readyForWeaning}");
        $this->info("Overdue: {$overdue}");
        $this->info("In Progress: {$inProgress}");
        $this->info("Completed: {$completed}");

        return 0;
    }
}
