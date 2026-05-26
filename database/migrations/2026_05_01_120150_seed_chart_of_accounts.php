<?php

use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the base chart of accounts during migrate (not only via --seed).
 *
 * Phase 4+ COA extension migrations parent new accounts under 1200
 * (Inventory) and 2000 (Accounts Payable). Those parents live in
 * ChartOfAccountsSeeder; without this migration, `php artisan migrate`
 * fails before seeders run.
 *
 * Idempotent: ChartOfAccountsSeeder uses updateOrCreate on (org, code).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            return;
        }

        (new ChartOfAccountsSeeder)->run();
    }

    public function down(): void
    {
        // Intentionally no-op: rolling back would delete accounts that
        // later migrations and journal history may reference.
    }
};
