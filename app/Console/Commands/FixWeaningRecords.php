<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Weaning;

class FixWeaningRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'weaning:fix-records';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix existing weaning records to set maturity_completed = false';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fixing existing weaning records...');

        // Update all existing weaning records to have maturity_completed = false
        $updated = DB::table('weanings')
            ->whereNull('maturity_completed')
            ->orWhere('maturity_completed', true)
            ->update(['maturity_completed' => false]);

        $this->info("Updated {$updated} weaning records to set maturity_completed = false");

        // Test the In Progress query
        $maturityAge = config('breeding.cycle.birth_to_maturity_days', 180);
        $inProgressCount = DB::table('pigs_management')
            ->whereNotNull('date_of_birth')
            ->whereRaw('DATEDIFF(NOW(), date_of_birth) < ?', [$maturityAge])
            ->whereExists(function($query) {
                $query->select(DB::raw(1))
                      ->from('weanings')
                      ->whereColumn('weanings.pig_id', 'pigs_management.id')
                      ->where('weanings.maturity_completed', false);
            })
            ->count();

        $this->info("Pigs now showing in In Progress: {$inProgressCount}");

        $this->info('✅ Weaning records fixed successfully!');

        return 0;
    }
}
