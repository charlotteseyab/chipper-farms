<?php

namespace App\Console\Commands\Finance;

use App\Models\Organization;
use App\Services\Finance\ChartOfAccountsProvisioner;
use Illuminate\Console\Command;

/**
 * Adds template accounts that farms are missing — every account for a farm
 * created before charts were provisioned automatically, or the accounts a
 * later finance migration added to the template. Shows what it would add
 * unless --apply is given.
 */
class SyncChartOfAccountsCommand extends Command
{
    protected $signature = 'finance:sync-chart {--organization= : Only this farm (id)} {--apply : Add the accounts}';

    protected $description = 'Give farms the template chart-of-accounts entries they are missing';

    public function handle(ChartOfAccountsProvisioner $provisioner): int
    {
        $farms = Organization::query()
            ->when($this->option('organization'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')->get(['id', 'name']);

        $rows = [];
        foreach ($farms as $farm) {
            $missing = $provisioner->missing((int) $farm->id);
            $codes = implode(', ', array_slice(array_map(fn ($a) => $a->code, $missing), 0, 10));
            $added = $this->option('apply') ? $provisioner->sync((int) $farm->id) : 0;
            $rows[] = [$farm->id, $farm->name, count($missing), count($missing) > 10 ? $codes.', …' : $codes, $this->option('apply') ? $added : '-'];
        }

        $this->table(['Farm', 'Name', 'Missing', 'Codes', 'Added'], $rows);
        if (! $this->option('apply')) {
            $this->line('Nothing changed. Run again with --apply to add these accounts.');
        }

        return self::SUCCESS;
    }
}
