<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrective backfill of organization_id on operational tables (CF-P0-005).
 *
 * Why this migration is required
 * ------------------------------
 * The policy layer now fails closed: a record whose organisation cannot be
 * resolved is denied rather than passed through. That is the correct security
 * posture, but it is only safe once existing rows actually carry an
 * organisation. Without this backfill, tightening the guard would convert a
 * confidentiality gap into an availability outage — legitimate users denied
 * access to their own historical records.
 *
 * Scope: deliberately narrow
 * --------------------------
 * Only tables where a null organisation is unambiguously a defect are touched.
 * A blanket backfill across all 74 nullable columns would be wrong: some tables
 * legitimately allow a null organisation for shared/global reference data — the
 * chart of accounts is the clearest case, where `accounts.organization_id IS
 * NULL` denotes a global account and existing tests assert that. Those are left
 * alone.
 *
 * Resolution rule
 * ---------------
 * Rows are assigned the single active organisation, and ONLY when exactly one
 * exists. That is the actual deployment shape (OrganizationSeeder creates one
 * "chipper-farms" organisation, and PigManagementPolicy's own docblock concedes
 * isolation "relies on ... Single-tenant production reality"). Where two or more
 * active organisations exist the migration makes no guess and no change: an
 * account or record attached to the wrong tenant is worse than one that is
 * still unresolved, because it silently grants cross-tenant reach. In that case
 * the migration reports what it skipped so an operator can resolve it
 * deliberately.
 *
 * Safety properties
 * -----------------
 *  - Idempotent: only rows WHERE organization_id IS NULL are written, so
 *    re-running is a no-op.
 *  - Resumable: each table is an independent statement; an interruption leaves
 *    completed tables done and the rest still null, and re-running continues.
 *  - Non-destructive: no column is dropped, no primary key changes, no NOT NULL
 *    constraint is added here. Adding NOT NULL is a separate, riskier step that
 *    requires production validation that zero nulls remain first; this migration
 *    is the prerequisite for that, not a substitute.
 *  - Reversible in the meaningful sense: down() is a no-op, because the
 *    pre-migration state was "unknown organisation" and restoring nulls would
 *    re-open the very gap this closes. Documented rather than silently omitted.
 */
return new class extends Migration
{
    /**
     * Operational tables where a null organisation is a tenancy defect.
     *
     * Every one of these backs a policy that calls sameOrganization(), or is a
     * direct parent for derived tenancy.
     */
    protected array $tables = [
        'pigs_management',
        'pig_sales',
        'pig_deaths',
        'purchase_requests',
        'purchase_stock_intakes',
        'storefront_listings',
        'feed_batches',
        'raw_material_stock_logs',
    ];

    public function up(): void
    {
        $organizationId = $this->resolveTargetOrganizationId();

        if ($organizationId === null) {
            // Nothing safe to do. Report and leave the data untouched.
            $this->report(
                'SKIPPED: could not identify a single active organisation. '
                . 'Operational rows with a null organization_id were left unchanged. '
                . 'Assign them deliberately before enabling NOT NULL constraints.'
            );

            return;
        }

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'organization_id')) {
                continue;
            }

            $updated = DB::table($table)
                ->whereNull('organization_id')
                ->update(['organization_id' => $organizationId]);

            if ($updated > 0) {
                $this->report("Backfilled {$updated} row(s) in {$table} to organization {$organizationId}.");
            }
        }

        $this->reportRemainingNulls();
    }

    /**
     * Intentionally a no-op.
     *
     * The prior state of these rows was "organisation unknown". Restoring nulls
     * on rollback would reopen the cross-tenant gap that the fail-closed policy
     * guard depends on being closed, and would break access to records users can
     * currently see. If a rollback is genuinely required, restore from the
     * pre-deployment backup instead — see
     * docs/operations/BACKUP_AND_RESTORE_RUNBOOK.md.
     */
    public function down(): void
    {
        // No-op by design. See the class docblock.
    }

    /**
     * The single active organisation, or null when the answer is ambiguous.
     */
    protected function resolveTargetOrganizationId(): ?int
    {
        if (! Schema::hasTable('organizations')) {
            return null;
        }

        $query = DB::table('organizations');

        // Respect soft deletes and the active flag where those columns exist —
        // this migration runs against schemas of different vintages.
        if (Schema::hasColumn('organizations', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (Schema::hasColumn('organizations', 'is_active')) {
            $query->where('is_active', true);
        }

        $ids = $query->orderBy('id')->limit(2)->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    protected function reportRemainingNulls(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'organization_id')) {
                continue;
            }

            $remaining = DB::table($table)->whereNull('organization_id')->count();

            if ($remaining > 0) {
                $this->report("WARNING: {$table} still has {$remaining} row(s) with a null organization_id.");
            }
        }
    }

    protected function report(string $message): void
    {
        // Migrations have no $this->command; write where an operator will see it.
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            fwrite(STDOUT, '  [org-backfill] ' . $message . PHP_EOL);
        }
    }
};
