<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfills users.organization_id for all users that currently have no
 * organization assignment.
 *
 * Safe to re-run: only touches users where organization_id IS NULL.
 *
 * Default behaviour: assigns to the single active organization if exactly
 * one exists. Fails fast (non-zero exit) if zero or more than one active
 * organization is found unless --organization is specified explicitly.
 *
 * Usage:
 *   php artisan chipper:backfill-user-organizations
 *   php artisan chipper:backfill-user-organizations --organization=1
 *   php artisan chipper:backfill-user-organizations --organization=chipper-farms
 *   php artisan chipper:backfill-user-organizations --dry-run
 */
class BackfillUserOrganizations extends Command
{
    protected $signature = 'chipper:backfill-user-organizations
                            {--organization= : Target organization id or slug. Auto-detects if exactly one active org exists.}
                            {--dry-run : Report what would change without writing.}';

    protected $description = 'Backfill users.organization_id for users that are not yet assigned to an organization.';

    public function handle(): int
    {
        $target = $this->resolveTargetOrganization();

        if ($target === null) {
            return self::FAILURE;
        }

        $unassignedCount = DB::table('users')->whereNull('organization_id')->count();

        if ($unassignedCount === 0) {
            $this->info('No users require backfill. All users already have an organization_id.');
            return self::SUCCESS;
        }

        $this->info("Found {$unassignedCount} user(s) without an organization_id.");
        $this->info("Target organization: #{$target->id} — {$target->name} ({$target->slug})");

        if ($this->option('dry-run')) {
            $this->warn('Dry run: no changes written.');
            return self::SUCCESS;
        }

        $updated = DB::transaction(function () use ($target) {
            return DB::table('users')
                ->whereNull('organization_id')
                ->update([
                    'organization_id' => $target->id,
                    'updated_at' => now(),
                ]);
        });

        $this->info("Backfill complete. {$updated} user(s) updated.");
        return self::SUCCESS;
    }

    protected function resolveTargetOrganization(): ?Organization
    {
        $specified = $this->option('organization');

        if ($specified !== null && $specified !== '') {
            $org = is_numeric($specified)
                ? Organization::find((int) $specified)
                : Organization::where('slug', $specified)->first();

            if ($org === null) {
                $this->error("Organization not found: {$specified}");
                return null;
            }

            if ($org->trashed() || ! $org->is_active) {
                $this->error("Organization {$specified} is inactive or soft-deleted. Refusing to backfill into it.");
                return null;
            }

            return $org;
        }

        $active = Organization::active()->get();

        if ($active->isEmpty()) {
            $this->error('No active organizations found. Seed at least one organization before running this command.');
            return null;
        }

        if ($active->count() > 1) {
            $this->error('Multiple active organizations found. Specify one explicitly with --organization=<id|slug>.');
            foreach ($active as $org) {
                $this->line("  #{$org->id}  {$org->slug}  {$org->name}");
            }
            return null;
        }

        return $active->first();
    }
}

