<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen `pig_sales.sale_purpose` to accept 'export'.
 *
 * THE DEFECT
 * ----------
 * `RecordPigSaleRequest` validates sale_purpose against:
 *
 *     market, breeding, slaughter, EXPORT, other
 *
 * The CHECK constraint added in 2026_04_27_110533 permits:
 *
 *     market, breeding, slaughter, CULL, other
 *
 * The two lists disagree on one value each. A user selecting "Export" passed
 * validation and was then rejected by the database — a 500 in production, every
 * time. It accounted for 8 of the failures found when the test database was
 * rebuilt from the migrations.
 *
 * WHY THE APPLICATION IS TREATED AS CORRECT
 * ------------------------------------------
 * `PigSale::getSalePurposeBadgeAttribute()` and `getSalePurposeIconAttribute()`
 * both have an 'export' arm and neither has a 'cull' arm, so the display layer
 * was written for a farm that exports. The constraint appears to predate that
 * and was never revisited.
 *
 * 'cull' IS RETAINED
 * ------------------
 * This migration is purely additive. Dropping 'cull' would reject any existing
 * row that uses it, and there is no way to know from here whether production
 * holds such rows. Widening cannot break existing data; narrowing can. If the
 * business confirms culls are never recorded, removing it is a separate,
 * evidence-backed change.
 *
 * SQLITE
 * ------
 * The original migration only added these constraints on mysql/pgsql, so SQLite
 * has none to widen. That asymmetry is why the SQLite suite passed while
 * MariaDB failed — and is worth recording as the reason a whole class of
 * integrity rule went untested.
 */
return new class extends Migration
{
    private const CONSTRAINT = 'pig_sales_sale_purpose_check';

    private const ALLOWED = "'market', 'breeding', 'slaughter', 'cull', 'export', 'other'";

    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            return;
        }

        $this->dropConstraintIfPresent();

        DB::statement(
            'ALTER TABLE pig_sales ADD CONSTRAINT ' . self::CONSTRAINT
            . ' CHECK (sale_purpose IN (' . self::ALLOWED . '))'
        );
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            return;
        }

        $this->dropConstraintIfPresent();

        // Restores the original list. Note this will FAIL if any row has been
        // saved with 'export' in the meantime — which is correct behaviour: a
        // rollback that silently discarded the constraint would leave the
        // database accepting values the schema claims to forbid.
        DB::statement(
            'ALTER TABLE pig_sales ADD CONSTRAINT ' . self::CONSTRAINT
            . " CHECK (sale_purpose IN ('market', 'breeding', 'slaughter', 'cull', 'other'))"
        );
    }

    /**
     * Drop the constraint only when it exists.
     *
     * Deliberately NOT a blanket try/catch. The original migration wrapped every
     * statement in `catch (\Throwable) { /* Ignore *\/ }`, which is how a
     * constraint can silently fail to be created and nobody finds out for two
     * years. Here the absence of the constraint is the only tolerated condition,
     * and anything else propagates.
     */
    private function dropConstraintIfPresent(): void
    {
        $exists = DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'pig_sales')
            ->where('constraint_name', self::CONSTRAINT)
            ->exists();

        if ($exists) {
            DB::statement('ALTER TABLE pig_sales DROP CONSTRAINT ' . self::CONSTRAINT);
        }
    }
};
