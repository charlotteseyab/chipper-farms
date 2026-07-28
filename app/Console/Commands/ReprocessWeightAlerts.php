<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PigWeight;
use App\Models\ExpectedWeightStandard;
use Illuminate\Support\Facades\Log;

class ReprocessWeightAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'weights:reprocess-alerts 
                            {--pig-type= : Specific pig type (breed:gender:stock_type, e.g., LW:M:standard)}
                            {--all : Reprocess all weight records}
                            {--dry-run : Show what would be processed without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reprocess weight alerts for existing weight records based on current weight standards';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Refresh Weight Alert Reprocessing Tool');
        $this->info('================================');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        if ($this->option('pig-type')) {
            $this->reprocessSpecificPigType();
        } elseif ($this->option('all')) {
            $this->reprocessAllWeightRecords();
        } else {
            $this->showUsage();
        }

        return 0;
    }

    /**
     * Reprocess alerts for a specific pig type
     */
    private function reprocessSpecificPigType(): void
    {
        $pigType = $this->option('pig-type');
        $parts = explode(':', $pigType);

        if (count($parts) !== 3) {
            $this->error("Invalid pig type format. Use: breed:gender:stock_type (e.g., LW:M:standard)");
            return;
        }

        [$breed, $gender, $stockType] = $parts;

        $this->info("Processing pig type: {$breed} {$gender} {$stockType}");

        if ($this->option('dry-run')) {
            $this->showDryRunInfo($breed, $gender, $stockType);
        } else {
            $processedCount = PigWeight::reprocessAlertsForPigType($breed, $gender, $stockType);
            $this->info("Reprocessed {$processedCount} weight record(s) for {$breed} {$gender} {$stockType}");
        }
    }

    /**
     * Reprocess all weight records
     */
    private function reprocessAllWeightRecords(): void
    {
        $this->info("Processing all weight records...");

        if ($this->option('dry-run')) {
            $this->showAllDryRunInfo();
        } else {
            $totalProcessed = 0;
            $pigTypes = $this->getAllPigTypes();

            $progressBar = $this->output->createProgressBar(count($pigTypes));
            $progressBar->start();

            foreach ($pigTypes as $pigType) {
                $processedCount = PigWeight::reprocessAlertsForPigType(
                    $pigType['breed'],
                    $pigType['gender'],
                    $pigType['stock_type']
                );
                $totalProcessed += $processedCount;
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);
            $this->info("Total reprocessed: {$totalProcessed} weight record(s)");
        }
    }

    /**
     * Show dry run information for specific pig type
     */
    private function showDryRunInfo(string $breed, string $gender, string $stockType): void
    {
        $weightCount = PigWeight::whereHas('pig', function($query) use ($breed, $gender, $stockType) {
            $query->where('breed', $breed)
                  ->where('gender', $gender)
                  ->where('stock_type', $stockType);
        })->count();

        $standardCount = ExpectedWeightStandard::where('breed', $breed)
            ->where('gender', $gender)
            ->where('stock_type', $stockType)
            ->where('is_active', true)
            ->count();

        $this->info("Analytics Dry Run Summary for {$breed} {$gender} {$stockType}:");
        $this->info("   • Weight records to process: {$weightCount}");
        $this->info("   • Active weight standards: {$standardCount}");
    }

    /**
     * Show dry run information for all pig types
     */
    private function showAllDryRunInfo(): void
    {
        $pigTypes = $this->getAllPigTypes();
        $totalWeightRecords = PigWeight::count();
        $totalStandards = ExpectedWeightStandard::where('is_active', true)->count();

        $this->info("Analytics Dry Run Summary:");
        $this->info("   • Total weight records: {$totalWeightRecords}");
        $this->info("   • Total active standards: {$totalStandards}");
        $this->info("   • Pig types to process: " . count($pigTypes));

        $this->newLine();
        $this->info("Pig types that would be processed:");
        foreach ($pigTypes as $pigType) {
            $weightCount = PigWeight::whereHas('pig', function($query) use ($pigType) {
                $query->where('breed', $pigType['breed'])
                      ->where('gender', $pigType['gender'])
                      ->where('stock_type', $pigType['stock_type']);
            })->count();

            $this->info("   • {$pigType['breed']} {$pigType['gender']} {$pigType['stock_type']}: {$weightCount} records");
        }
    }

    /**
     * Get all unique pig types from the database
     */
    private function getAllPigTypes(): array
    {
        return \App\Models\PigManagement::select('breed', 'gender', 'stock_type')
            ->distinct()
            ->get()
            ->map(function($pig) {
                return [
                    'breed' => $pig->breed,
                    'gender' => $pig->gender,
                    'stock_type' => $pig->stock_type
                ];
            })
            ->toArray();
    }

    /**
     * Show usage information
     */
    private function showUsage(): void
    {
        $this->info("Usage Examples:");
        $this->info("  php artisan weights:reprocess-alerts --pig-type=LW:M:standard");
        $this->info("  php artisan weights:reprocess-alerts --all");
        $this->info("  php artisan weights:reprocess-alerts --pig-type=LW:M:standard --dry-run");
        $this->info("  php artisan weights:reprocess-alerts --all --dry-run");
        $this->newLine();
        $this->info("Options:");
        $this->info("  --pig-type=breed:gender:stock_type  Process specific pig type");
        $this->info("  --all                              Process all weight records");
        $this->info("  --dry-run                          Show what would be processed without making changes");
    }
}
