<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tax profiles and their components (Phase 2, blueprint §30).
 *
 * WHY A PROFILE LAYER ON TOP OF `tax_codes`
 * -----------------------------------------
 * `tax_codes` already models a single levy well: rate, recoverable, withholding,
 * effective dates, GL hint. What it cannot express is the thing Ghana actually
 * requires — that VAT, NHIL and GETFund are THREE levies applied together, on
 * the SAME base, as one commercial decision.
 *
 * Today the seeder's own docblock says "we seed each code separately and expect
 * Phase 1 to apply the combined rate at posting time via a tax group". That tax
 * group never got built, so every caller would have to know to pick three codes
 * and combine them correctly. That is exactly the kind of knowledge that ends up
 * implemented three different ways.
 *
 * A profile is that missing group: one selectable thing a user picks, which
 * knows which levies it contains and how they combine.
 *
 * COMPONENTS CARRY THEIR OWN RATE
 * -------------------------------
 * A component references a tax_code (for the GL account hint and lineage) but
 * stores its own `rate` and `rate_type`.
 *
 * This looks like duplication and is deliberate. If components read the rate
 * live from tax_codes, then correcting a typo in a tax code's rate would
 * silently change what every historical profile meant. Blueprint §13 requires
 * "keep effective dates so older transactions remain explainable"; that only
 * holds if the profile's own definition is stable.
 *
 * The transaction-level snapshot (Phase 2, taken at posting time) is a third
 * layer of the same principle: profile definitions can be superseded, but a
 * posted invoice must never recalculate.
 *
 * BASE MODE IS EXPLICIT, NOT INFERRED
 * -----------------------------------
 * `base_mode` is stored per component rather than derived from a profile-level
 * flag. Ghana pre-2026 computed NHIL and GETFund on the net amount and then VAT
 * on (net + NHIL + GETFund) — a genuine cascade. From 2026 all three sit on the
 * same base. Both shapes must remain expressible, because the historical profile
 * has to keep explaining historical invoices.
 *
 * ORGANIZATION_ID IS NOT NULL
 * ---------------------------
 * `tax_codes` allows null, which was how the original seeder coped with an empty
 * organisations table. New tables in this programme do not repeat that: a tax
 * profile with no organisation cannot be resolved and a null would behave like a
 * wildcard across tenants.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tax_profiles')) {
            Schema::create('tax_profiles', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('organization_id');

                $table->string('code', 40);
                $table->string('name');
                $table->string('description', 500)->nullable();

                // standard | zero_rated | exempt | no_tax
                //
                // zero_rated and exempt both produce zero tax but are NOT the
                // same thing: zero-rated supplies allow input tax recovery,
                // exempt supplies do not. Collapsing them would misstate the
                // recoverable input tax position.
                $table->string('treatment', 20)->default('standard');

                // sale | purchase | both
                $table->string('applies_to', 20)->default('both');

                // inclusive | exclusive — whether prices quoted against this
                // profile already contain the tax.
                $table->string('price_mode', 20)->default('exclusive');

                // Effective dating. effective_from is NOT NULL because an
                // undated profile cannot be selected correctly for a
                // back-dated transaction.
                $table->date('effective_from');
                $table->date('effective_to')->nullable();

                $table->boolean('is_default_sales')->default(false);
                $table->boolean('is_default_purchase')->default(false);
                $table->boolean('is_active')->default(true);

                $table->timestamps();

                // A code may repeat across effective periods — that is how
                // "Ghana VAT standard" exists both pre- and post-2026 — but not
                // within one period.
                $table->unique(['organization_id', 'code', 'effective_from'], 'tax_profiles_org_code_from_unique');
                $table->index(['organization_id', 'is_active', 'applies_to'], 'tax_profiles_lookup_idx');
                $table->index(['organization_id', 'effective_from', 'effective_to'], 'tax_profiles_effective_idx');

                $table->foreign('organization_id')
                    ->references('id')->on('organizations')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('tax_profile_components')) {
            Schema::create('tax_profile_components', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('tax_profile_id');

                // Lineage to the underlying levy, used for the GL account hint.
                // Nullable so a profile can express a levy that has no tax_code
                // row yet without blocking the profile.
                $table->unsignedBigInteger('tax_code_id')->nullable();

                $table->string('code', 40);
                $table->string('label');

                // percent | fixed
                $table->string('rate_type', 20)->default('percent');
                $table->decimal('rate', 8, 4)->default(0);

                // net              — computed on the profile's taxable base
                // compound_on_previous — computed on base + all prior components
                $table->string('base_mode', 30)->default('net');

                $table->unsignedSmallInteger('calculation_order')->default(10);

                $table->boolean('is_recoverable')->default(false);

                $table->string('gl_account_hint', 50)->nullable();

                $table->timestamps();

                $table->unique(['tax_profile_id', 'code'], 'tax_profile_components_unique');
                $table->index(['tax_profile_id', 'calculation_order'], 'tax_profile_components_order_idx');

                $table->foreign('tax_profile_id')
                    ->references('id')->on('tax_profiles')
                    ->cascadeOnDelete();

                $table->foreign('tax_code_id')
                    ->references('id')->on('tax_codes')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_profile_components');
        Schema::dropIfExists('tax_profiles');
    }
};
