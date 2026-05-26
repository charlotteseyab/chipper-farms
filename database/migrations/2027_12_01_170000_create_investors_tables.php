<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9 — investor master + capital accounts.
 *
 * investors:
 *   One row per investor (person or entity). Optionally linked to a
 *   Counterparty for shared contact/identity. Holds the investor's class
 *   (equity / preferred) and current ownership percentage (for ownership-pct
 *   profit allocation). A linked Phase 8 loan is referenced for a unified
 *   investor view but NOT duplicated here.
 *
 * investor_capital_accounts:
 *   The subledger behind 3100 Investor Capital Accounts. One row per investor
 *   (a single capital account per investor in v1; the table allows more later
 *   via the label column). Tracks running contributed / allocated /
 *   distributed figures and the current capital balance. The sum of
 *   current_balance across rows must equal the 3100 GL control balance —
 *   the reconciliation report asserts this.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('investors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();

            $table->string('name', 200);
            $table->string('reference', 50)->nullable();          // e.g. INV-001
            $table->string('investor_class', 20)->default('equity'); // equity | preferred
            $table->unsignedBigInteger('counterparty_id')->nullable();
            $table->unsignedBigInteger('linked_loan_id')->nullable(); // Phase 8 investor loan, if any

            // Ownership percentage for ownership-pct allocation. Stored as a
            // fraction 0..1 at 6 dp (e.g. 0.250000 = 25%).
            $table->decimal('ownership_percentage', 9, 6)->default(0);

            // Preferred-share terms (used only when investor_class = preferred).
            $table->decimal('preferred_share_capital', 18, 2)->default(0);
            $table->decimal('preferred_return_rate', 9, 6)->default(0); // annual, fraction

            $table->string('status', 20)->default('active')->index(); // active | inactive
            $table->string('contact_email', 200)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('notes', 2000)->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'reference'], 'investors_org_ref_unique');
        });

        Schema::create('investor_capital_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('investor_id')->index();
            $table->string('label', 100)->default('Capital Account');

            $table->decimal('total_contributed', 18, 2)->default(0);
            $table->decimal('total_allocated', 18, 2)->default(0);   // profit allocated in
            $table->decimal('total_distributed', 18, 2)->default(0);
            $table->decimal('current_balance', 18, 2)->default(0);

            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();

            $table->unique(['investor_id', 'label'], 'ica_investor_label_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_capital_accounts');
        Schema::dropIfExists('investors');
    }
};
