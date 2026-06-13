<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront Phase 2 — quote requests.
 *
 * A quote is a customer's request for pricing on bulk / breeding / complex
 * orders. It is NOT an order and posts nothing to finance. It carries enough
 * structure to (a) be triaged by a salesperson, (b) be priced, and (c) later
 * (Phase 5) be converted into an order.
 *
 * counterparty_id and customer_user_id are nullable: a guest can request a
 * quote with just contact details; an authenticated customer's quote links to
 * their CustomerProfile counterparty. Conversion (Phase 5) resolves a guest to
 * a counterparty.
 *
 * Money: quoted_total decimal(18,2); target_unit_price decimal(18,4) — finance
 * precision, ready to feed an order later.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('storefront_quotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->string('reference', 60);

            // Requester (either/both may be present).
            $table->unsignedBigInteger('counterparty_id')->nullable()->index();
            $table->unsignedBigInteger('customer_user_id')->nullable()->index();
            $table->string('contact_name', 200);
            $table->string('contact_email', 200)->nullable();
            $table->string('contact_phone', 60)->nullable();

            // Request body.
            $table->string('source', 20)->default('listing'); // listing|bulk
            $table->string('fulfilment_preference', 20)->default('pickup'); // pickup|delivery
            $table->string('delivery_city', 120)->nullable();
            $table->string('delivery_region', 120)->nullable();
            $table->text('message')->nullable();

            // Triage / pricing.
            $table->string('status', 20)->default('requested')->index();
            // requested|reviewing|quoted|accepted|declined|expired|converted
            $table->text('staff_notes')->nullable();
            $table->decimal('quoted_total', 18, 2)->nullable();
            $table->string('currency_code', 3)->default('GHS');
            $table->date('valid_until')->nullable();
            $table->unsignedBigInteger('assigned_to_user_id')->nullable()->index();
            $table->unsignedBigInteger('converted_order_id')->nullable(); // set in Phase 5

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'reference'], 'storefront_quotes_org_ref_uq');
            $table->index(['organization_id', 'status'], 'storefront_quotes_org_status_idx');
        });

        Schema::create('storefront_quote_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('quote_id')->index();

            // Soft listing link (listing may be archived/removed later).
            $table->unsignedBigInteger('listing_id')->nullable()->index();
            $table->string('kind', 30)->nullable();
            $table->string('description', 300);
            $table->decimal('quantity', 18, 4)->default(1);
            $table->string('quantity_unit', 20)->default('unit');
            $table->decimal('target_unit_price', 18, 4)->nullable();
            $table->string('line_note', 300)->nullable();

            $table->timestamps();

            $table->foreign('quote_id')
                ->references('id')->on('storefront_quotes')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_quote_items');
        Schema::dropIfExists('storefront_quotes');
    }
};
