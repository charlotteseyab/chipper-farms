<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2a — counterparty_addresses.
 *
 * Multiple addresses per counterparty. Common cases:
 *   - Billing address (where invoices are sent)
 *   - Shipping/delivery address (where pigs are delivered)
 *   - Farm pickup point (when buyer collects from farm)
 *   - Office / HQ address (for institutional customers)
 *
 * The legacy address_line_1 / city / region / country columns on
 * counterparties remain populated for backward compatibility — they're
 * treated as the primary address. This table is the authoritative source
 * once populated; the controller surfaces addresses with a "use as primary"
 * action that mirrors the chosen address back into the legacy columns.
 *
 * is_default + address_type pair enforces "one default per type per
 * counterparty" — checked in application logic since DB-level partial
 * unique indexes aren't portable across MySQL/Postgres/SQLite.
 *
 * Idempotent and additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('counterparty_addresses')) {
            return;
        }

        Schema::create('counterparty_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->unsignedBigInteger('counterparty_id');
            $table->foreign('counterparty_id')
                  ->references('id')
                  ->on('counterparties')
                  ->cascadeOnDelete();

            $table->string('address_type', 30);
            // 'billing' | 'shipping' | 'pickup' | 'office' | 'other'

            $table->string('label', 100)->nullable();
            // Free-text label, e.g. "Accra office", "Kumasi warehouse"

            $table->string('attention_to', 100)->nullable();
            // c/o name on the address line

            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2)->default('GH');

            // Optional GPS coordinates — Ghana now has GhanaPost GPS codes
            // and many businesses include them. Useful for delivery routing.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('ghana_post_gps', 20)->nullable();
            // Format like 'GA-123-4567'.

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->text('notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['counterparty_id', 'address_type', 'is_default'], 'counterparty_addresses_lookup_idx');
            $table->index(['organization_id', 'is_active'], 'counterparty_addresses_org_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counterparty_addresses');
    }
};
