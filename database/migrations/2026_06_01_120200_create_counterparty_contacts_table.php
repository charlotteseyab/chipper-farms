<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2a — counterparty_contacts.
 *
 * Multiple named contacts per counterparty. Common cases:
 *   - Procurement officer (places orders)
 *   - Finance officer (handles payments)
 *   - Manager (escalation contact)
 *   - Driver (delivery coordination)
 *
 * Free-text role; not enforced via reference table because real-world
 * roles vary too much to normalise without losing fidelity.
 *
 * The legacy primary_contact_name / phone / email columns on counterparties
 * remain. This table is supplementary — adds contacts beyond the primary.
 *
 * Idempotent and additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('counterparty_contacts')) {
            return;
        }

        Schema::create('counterparty_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->unsignedBigInteger('counterparty_id');
            $table->foreign('counterparty_id')
                  ->references('id')
                  ->on('counterparties')
                  ->cascadeOnDelete();

            $table->string('name', 100);
            $table->string('role', 100)->nullable();
            // 'Procurement officer', 'Manager', 'Driver', etc.

            $table->string('phone', 50)->nullable();
            $table->string('phone_alt', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('whatsapp', 50)->nullable();
            // Whatsapp is the primary business channel in Ghana for many SMEs.

            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);

            $table->text('notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['counterparty_id', 'is_active'], 'counterparty_contacts_lookup_idx');
            $table->index('phone', 'counterparty_contacts_phone_idx');
            $table->index('email', 'counterparty_contacts_email_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counterparty_contacts');
    }
};
