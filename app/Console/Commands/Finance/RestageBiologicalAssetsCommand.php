<?php

namespace App\Console\Commands\Finance;

use App\Models\Finance\BiologicalAssetStageBalance;
use App\Models\Finance\BiologicalAssetStageTransfer;
use App\Models\Group;
use App\Models\User;
use App\Services\Finance\BiologicalAssets\BiologicalAssetStageResolver;
use App\Services\Finance\BiologicalAssets\BiologicalAssetSubledgerService;
use App\Support\CurrentOrganization;
use Illuminate\Console\Command;

/**
 * Periodically restage biological assets: when a group's representative
 * stage (age-driven) has advanced beyond the stage currently holding its
 * cost, move the carrying value to the new stage account.
 *
 * Stage sub-progressions (piglet→weaner→grower→finisher) are age-driven and
 * continuous, so there is no per-pig event for them. This command — run on
 * a schedule (e.g. daily/weekly) — detects the drift and posts the transfer
 * journals via BiologicalAssetSubledgerService::transferStage.
 *
 * Discrete promotions (standard→breeding) are handled separately by the
 * PigLifecycleStageChanged subscriber; this command focuses on the
 * age-based fattening path but will also reconcile any group whose resolved
 * stage differs from where its cost sits.
 *
 * Usage:
 *   php artisan finance:restage-biological-assets [--dry-run] [--actor=<user_id>]
 *
 * --dry-run prints the transfers it would post without posting them.
 * --actor   the user id to attribute the system postings to (defaults to
 *           the first admin/superuser found).
 */
class RestageBiologicalAssetsCommand extends Command
{
    protected $signature = 'finance:restage-biological-assets
                            {--dry-run : Show planned transfers without posting}
                            {--actor= : User id to attribute postings to}';

    protected $description = 'Move biological-asset carrying cost to the correct stage account as groups age.';

    public function handle(
        BiologicalAssetStageResolver $resolver,
        BiologicalAssetSubledgerService $subledger,
        CurrentOrganization $currentOrg,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $actor = $this->resolveActor();

        if (! $actor && ! $dryRun) {
            $this->error('No actor user found to attribute postings to. Pass --actor=<user_id> or create an admin user.');
            return self::FAILURE;
        }

        $organizationId = $currentOrg->id();

        // Every group that currently holds cost in some stage.
        $balances = BiologicalAssetStageBalance::query()
            ->when($organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'))
            ->where('carrying_value', '>', 0)
            ->get();

        $planned = 0;
        $posted  = 0;

        foreach ($balances->groupBy('group_id') as $groupId => $groupBalances) {
            $targetStage = $resolver->stageForGroup((int) $groupId);

            foreach ($groupBalances as $balance) {
                if ($balance->stage_key === $targetStage) {
                    continue; // already in the right stage
                }
                if (bccomp((string) $balance->carrying_value, '0', 4) <= 0) {
                    continue;
                }

                $planned++;
                $this->line(sprintf(
                    '[group %d] %s → %s : GHS %s (%d pigs, %s kg)',
                    $groupId, $balance->stage_key, $targetStage,
                    number_format((float) $balance->carrying_value, 2),
                    (int) $balance->pig_count,
                    number_format((float) $balance->weight_kg, 2),
                ));

                if ($dryRun) {
                    continue;
                }

                $transfer = $subledger->transferStage(
                    groupId:   (int) $groupId,
                    fromStage: $balance->stage_key,
                    toStage:   $targetStage,
                    actor:     $actor,
                    reason:    BiologicalAssetStageTransfer::REASON_STAGE_PROGRESSION,
                    pigCount:  (int) $balance->pig_count,
                    weightDelta: (string) $balance->weight_kg,
                );

                if ($transfer) {
                    $posted++;
                }
            }
        }

        if ($dryRun) {
            $this->info("Dry run: {$planned} stage transfer(s) would be posted.");
        } else {
            $this->info("Restage complete: {$posted} transfer(s) posted ({$planned} planned).");
        }

        return self::SUCCESS;
    }

    protected function resolveActor(): ?User
    {
        if ($id = $this->option('actor')) {
            return User::find((int) $id);
        }

        // Best-effort: first admin/superuser.
        return User::query()
            ->where('role', 'admin')
            ->orWhere('role', 'superuser')
            ->first()
            ?? User::query()->first();
    }
}
