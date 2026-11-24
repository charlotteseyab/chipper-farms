<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Stop MySQL/MariaDB rewriting "when it happened" columns on every update.
 *
 * With explicit_defaults_for_timestamp off (the MariaDB default), the first
 * TIMESTAMP NOT NULL column of a table is silently created as
 * DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP. For a column like
 * feed_issues.issued_at that means any later update — posting it to the
 * ledger, reversing it — moves the feeding to the moment of the update, and
 * planned-vs-actual, stock history and event dates drift. The migrations never
 * asked for this; the server added it.
 *
 * This keeps each column's default and drops the ON UPDATE clause. It finds
 * the affected columns itself (updated_at is left alone: changing on update
 * is its job). SQLite never adds the clause, so there it does nothing.
 *
 * Dates already overwritten before this ran cannot be recovered.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $columns = DB::select("SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE, COLUMN_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = 'timestamp' AND LOWER(EXTRA) LIKE '%on update%' AND COLUMN_NAME <> 'updated_at'");

        foreach ($columns as $column) {
            $null = $column->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE `{$column->TABLE_NAME}` MODIFY `{$column->COLUMN_NAME}` {$column->COLUMN_TYPE} {$null} DEFAULT CURRENT_TIMESTAMP");
        }
    }

    public function down(): void
    {
        // Deliberately not restored: rewriting event dates on update is a defect, not a setting.
    }
};
