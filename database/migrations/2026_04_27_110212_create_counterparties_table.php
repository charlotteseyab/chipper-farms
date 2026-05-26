<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.8 — counterparties.
 *
 * A unified customer/supplier reference table. Finance modules often split
 * these into two tables, but in practice on a small operation the same
 * entity is often BOTH a customer and a supplier (e.g., a feed supplier
 * who also buys cull pigs). A single table with a `type` flag keeps data
 * canonical and makes ledger consolidation simpler.
 *
 * type values:
 *   'customer' — AR side only
 *   'supplier' — AP side only
 *   'both'     — AR + AP
 *
 * This table is intentionally subledger-ready but NOT yet required.
 * Source tables (pig_sales, purchase_requests) will add nullable FKs.
 * Free-text buyer_name / supplier fields remain populated alongside for
 * backward compatibility until a migration backfills the linkages.
 *
 * Safe and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('counterparties')) {
            return;
        }

        Schema::create('counterparties', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id')->nullable();

            $table->string('type', 20)->default('both');
            // Enforced via CHECK where supported; application validates also.

            $table->string('code', 50)->nullable();   // Short human-friendly reference, e.g. 'CUST-001'
            $table->string('name');                   // Required — display name
            $table->string('legal_name')->nullable(); // Registered name where different
            $table->string('tax_id', 50)->nullable(); // TIN / VAT number
            $table->string('registration_number', 50)->nullable();

            // Contact
            $table->string('primary_contact_name')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 100)->nullable();

            // Address
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('country', 2)->default('GH');  // ISO 3166-1 alpha-2

            // Finance defaults — stored as hints; authoritative rules live in
            // posting-time logic when Phase 1 lands.
            $table->unsignedBigInteger('default_payment_method_id')->nullable();
            $table->unsignedBigInteger('default_tax_code_id')->nullable();
            $table->integer('payment_terms_days')->nullable();  // net-N days
            $table->decimal('credit_limit', 15, 2)->nullable();

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['organization_id', 'type', 'is_active'], 'counterparties_org_type_active_idx');
            $table->index(['organization_id', 'name'], 'counterparties_org_name_idx');
            $table->unique(['organization_id', 'code'], 'counterparties_org_code_unique');
        });

        // CHECK constraint on type. MySQL 8+ and SQLite support it; MariaDB
        // 10.2.1+ supports it. Guard by driver.
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'sqlite', 'pgsql'], true)) {
            try {
                \DB::statement(
                    "ALTER TABLE counterparties ADD CONSTRAINT counterparties_type_check "
                    . "CHECK (type IN ('customer', 'supplier', 'both'))"
                );
            } catch (\Throwable $e) {
                // Older MySQL versions may reject; application validation is still in force.
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'sqlite', 'pgsql'], true)) {
            try {
                \DB::statement('ALTER TABLE counterparties DROP CONSTRAINT counterparties_type_check');
            } catch (\Throwable $e) {
                // Constraint may never have been created.
            }
        }

        Schema::dropIfExists('counterparties');
    }
};