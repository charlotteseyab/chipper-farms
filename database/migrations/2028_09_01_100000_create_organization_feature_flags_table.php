<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organisation feature flags (Phase 1, blueprint §32).
 *
 * WHY A DEDICATED TABLE AND NOT `system_configs`
 * ----------------------------------------------
 * The obvious shortcut is to store flags as `Setting::set('feature.x', true)`,
 * which is the established key/value pattern in this codebase. That would be
 * wrong here, and Phase 0 §6 established why:
 *
 *   `system_configs` has columns (id, key, value, created_at, updated_at).
 *   There is NO organization_id.
 *
 * Every setting in that table is therefore global across tenants. That is
 * already a latent defect for pricing — `sales.pricing.live_weight_price` is
 * read by PigSale to price real sales, so two organisations cannot currently
 * have different pig prices. Building per-organisation module configuration on
 * top of a store that has no organisation column would guarantee that enabling
 * a module for one farm enables it for every farm.
 *
 * So this table carries the tenant column from the start.
 *
 * ORGANIZATION_ID IS NOT NULL
 * ---------------------------
 * Same reasoning as `sales_returns`: a new table has no legacy rows to
 * accommodate, so the constraint is enforced now rather than backfilled after
 * the fact. A flag with no organisation has no meaning — it cannot be resolved,
 * and a null would silently behave like a wildcard.
 *
 * ABSENT ROW MEANS "NOT CONFIGURED", NOT "DISABLED"
 * -------------------------------------------------
 * The service resolves an absent row to the feature's catalogue default rather
 * than to false. This matters for backward compatibility: 193 migrations of
 * existing functionality must not vanish the moment this table is created and
 * is still empty. The accompanying backfill migration writes explicit rows for
 * existing organisations so their behaviour is recorded rather than inferred.
 *
 * AUDIT COLUMNS ARE SEPARATE FOR ENABLE AND DISABLE
 * -------------------------------------------------
 * Blueprint §32 requires "changes record actor/date/old/new". Keeping
 * enabled_by/enabled_at and disabled_by/disabled_at as distinct columns means
 * the last transition in each direction survives on the row itself, so the
 * common question "who turned this off and when" is answerable without joining
 * an audit log.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organization_feature_flags')) {
            return;
        }

        Schema::create('organization_feature_flags', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');

            // Dotted key from FeatureCatalog, e.g. 'finance.quick_expense'.
            // Deliberately a string rather than an enum: the catalogue is code,
            // and adding a feature must not require a schema migration.
            $table->string('feature_key', 100);

            $table->boolean('enabled')->default(false);

            // Per-organisation feature configuration (hold minutes, category
            // lists, and so on). Null when the feature takes no configuration.
            $table->json('configuration')->nullable();

            // Which preset last wrote this row, if any. Lets the settings UI
            // show "you are on Essentials, with 2 manual changes" rather than
            // just a list of booleans.
            $table->string('applied_preset', 60)->nullable();

            $table->unsignedBigInteger('enabled_by')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->unsignedBigInteger('disabled_by')->nullable();
            $table->timestamp('disabled_at')->nullable();

            $table->timestamps();

            // One row per feature per organisation. This is the correctness
            // anchor: without it, two concurrent preset applications could
            // leave an organisation with conflicting duplicate rows and the
            // resolved value would depend on row order.
            $table->unique(['organization_id', 'feature_key'], 'org_feature_unique');

            // The hot path is "resolve every flag for this organisation" when
            // building navigation, so lead with organization_id.
            $table->index(['organization_id', 'enabled'], 'org_feature_enabled_idx');

            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_feature_flags');
    }
};
