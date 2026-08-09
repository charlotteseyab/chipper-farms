<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront settings and delivery zones (Phase 8, blueprint §34).
 *
 * WHY NOT `system_configs`
 * -----------------------
 * `StorefrontSettings` currently reads the `storefront.*` namespace out of the
 * shared `system_configs` key/value table. Phase 0 §6 established that table has
 * no `organization_id`. Every farm on the installation therefore shares one
 * store name, one support email, one pickup address — and, more seriously, one
 * `sales.pricing.*` pair that prices real sales. That is logged as IMP-001.
 *
 * Phase 1 answered the same question for feature flags by giving them their own
 * organisation-scoped table rather than extending `Setting`. This does the same
 * for storefront configuration, for the same reason, and closes the storefront
 * half of IMP-001 in the process.
 *
 * TYPED COLUMNS, NOT KEY/VALUE
 * ----------------------------
 * A second key/value store would inherit the first one's real defect: nothing
 * can be validated at the storage boundary, so `checkout_hold_minutes` can hold
 * "soon" and `default_tax_profile_id` can point at another organisation's
 * profile. Columns give the database a say — a foreign key on the tax profile,
 * a bounded integer on the hold, a unique organisation.
 *
 * ONE ROW PER ORGANISATION, ABSENT MEANS "NOT CONFIGURED"
 * -------------------------------------------------------
 * As with feature flags, an absent row resolves to the code default rather than
 * to empty. A farm that has never opened the settings screen must still have a
 * working shop.
 *
 * WHAT IS *NOT* HERE
 * ------------------
 * The on/off switches blueprint §34 lists — store enabled, payment methods
 * enabled, public categories enabled, delivery enabled, guest checkout enabled
 * — are already `organization_feature_flags` rows (`storefront.enabled`,
 * `storefront.paystack`, `storefront.cod`, `storefront.live_pigs`, …). Adding a
 * second copy here would create two switches for one behaviour, and an
 * administrator who turned COD on in one place and found it still off in the
 * other. The settings screen writes the flags; this table holds only the values
 * the flags have no room for.
 *
 * Paystack mode/status is likewise absent by design: it is derived from
 * `config('services.paystack')` and displayed as health. Copying a gateway
 * credential's shape into a tenant table invites someone to store the secret
 * next to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('storefront_settings')) {
            Schema::create('storefront_settings', function (Blueprint $table) {
                $table->id();

                // NOT NULL from the start. A settings row with no organisation
                // cannot be resolved and would behave like a wildcard — which is
                // precisely the system_configs defect this table exists to avoid.
                $table->unsignedBigInteger('organization_id');

                // Is the public shop currently accepting visitors?
                //
                // Distinct from the `storefront.enabled` feature flag, and the
                // distinction is load-bearing. The flag says whether this
                // organisation has a storefront at all — turning it off also
                // takes away the back-office listings workspace, and because
                // every other storefront flag depends on it, switching it off
                // cascades and loses the farm's payment and category choices.
                // A farm pausing its shop for a season wants none of that. This
                // column is the day-to-day open/closed sign; the flag is
                // whether the shop exists.
                $table->boolean('public_shop_open')->default(true);

                // Store identity (previously storefront.store_name etc.).
                $table->string('store_name', 150)->nullable();
                $table->string('support_email', 190)->nullable();
                $table->string('support_phone', 60)->nullable();
                $table->string('default_currency', 3)->default('GHS');
                $table->text('pickup_address')->nullable();
                $table->string('hero_headline', 255)->nullable();
                $table->string('hero_subheadline', 255)->nullable();

                // Tax presentation. 'exclusive' shows tax added at checkout;
                // 'inclusive' shows a price that already contains it. This
                // changes what the buyer is shown, never what is posted — the
                // tax profile decides that.
                $table->string('tax_display_mode', 20)->default('exclusive');

                // Default sales tax profile for storefront orders. Nullable
                // because a farm below the VAT threshold charges none.
                $table->unsignedBigInteger('default_tax_profile_id')->nullable();

                // How long a checkout hold survives (Phase 10 consumes this).
                // Stored here rather than in config so each farm can match its
                // own collection rhythm.
                $table->unsignedSmallInteger('checkout_hold_minutes')->default(30);

                // Customer-facing refund policy, shown at checkout and on the
                // order page. Blueprint §42 requires the buyer to be told the
                // terms before paying, not after asking for money back.
                $table->text('refund_policy')->nullable();

                $table->unsignedBigInteger('updated_by')->nullable();

                $table->timestamps();

                // One row per organisation. Without this, two concurrent saves
                // leave duplicates and the resolved value depends on row order —
                // the exact defect IMP-025 found in the chart of accounts.
                $table->unique('organization_id', 'storefront_settings_org_unique');

                $table->foreign('organization_id')
                    ->references('id')->on('organizations')
                    ->cascadeOnDelete();
            });

            // Added separately: tax_profiles is created by a Phase 2 migration
            // that runs earlier, but a fresh install ordering change should
            // degrade to "no FK" rather than to a failed migration.
            if (Schema::hasTable('tax_profiles')) {
                Schema::table('storefront_settings', function (Blueprint $table) {
                    $table->foreign('default_tax_profile_id')
                        ->references('id')->on('tax_profiles')
                        ->nullOnDelete();
                });
            }
        }

        if (! Schema::hasTable('storefront_delivery_zones')) {
            Schema::create('storefront_delivery_zones', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('organization_id');

                $table->string('name', 120);
                $table->string('description', 255)->nullable();

                // decimal(18,2) to match storefront_orders.delivery_fee. A zone
                // fee lands on that column unchanged, so a narrower type here
                // would silently truncate on the way in.
                $table->decimal('fee', 18, 2)->default(0);

                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('display_order')->default(0);

                $table->timestamps();

                $table->unique(['organization_id', 'name'], 'storefront_delivery_zones_org_name_unique');
                $table->index(['organization_id', 'is_active', 'display_order'], 'storefront_delivery_zones_lookup_idx');

                $table->foreign('organization_id')
                    ->references('id')->on('organizations')
                    ->cascadeOnDelete();
            });
        }

        // Which zone priced an order, snapshotted by name.
        //
        // The id alone would not survive a rename or a re-price: a farm that
        // raises the Kumasi fee in March must still be able to explain what a
        // February order was charged and why. The name is copied at order time
        // for exactly that reason, and the id is kept nullable-on-delete so
        // removing a zone does not orphan history.
        if (Schema::hasTable('storefront_orders') && ! Schema::hasColumn('storefront_orders', 'delivery_zone_id')) {
            Schema::table('storefront_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('delivery_zone_id')->nullable()->after('delivery_fee');
                $table->string('delivery_zone_name', 120)->nullable()->after('delivery_zone_id');

                $table->foreign('delivery_zone_id')
                    ->references('id')->on('storefront_delivery_zones')
                    ->nullOnDelete();
            });
        }

        $this->backfillFromGlobalSettings();
    }

    /**
     * Carry the existing global `storefront.*` values onto every organisation.
     *
     * Idempotent and resumable: organisations that already have a row are
     * skipped, so a re-run cannot overwrite an administrator's later choices,
     * and a partial run resumes where it stopped. Chunked so a large tenant
     * list does not load into memory.
     *
     * The values were global, so every organisation legitimately receives the
     * same ones — that is what they were already serving. Divergence starts the
     * moment an administrator edits one.
     */
    protected function backfillFromGlobalSettings(): void
    {
        if (! Schema::hasTable('organizations') || ! Schema::hasTable('system_configs')) {
            return;
        }

        $legacy = DB::table('system_configs')
            ->where('key', 'like', 'storefront.%')
            ->pluck('value', 'key');

        if ($legacy->isEmpty()) {
            return;
        }

        $now = now();

        $row = [
            'store_name'       => $this->legacyString($legacy, 'storefront.store_name'),
            'support_email'    => $this->legacyString($legacy, 'storefront.support_email'),
            'support_phone'    => $this->legacyString($legacy, 'storefront.support_phone'),
            'default_currency' => $this->legacyString($legacy, 'storefront.default_currency') ?? 'GHS',
            'pickup_address'   => $this->legacyString($legacy, 'storefront.pickup_address'),
            'hero_headline'    => $this->legacyString($legacy, 'storefront.hero_headline'),
            'hero_subheadline' => $this->legacyString($legacy, 'storefront.hero_subheadline'),
        ];

        DB::table('organizations')->orderBy('id')->chunkById(200, function ($organizations) use ($row, $now): void {
            foreach ($organizations as $organization) {
                $exists = DB::table('storefront_settings')
                    ->where('organization_id', $organization->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('storefront_settings')->insert($row + [
                    'organization_id' => $organization->id,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }
        });
    }

    /**
     * `Setting::get()` json-decodes; this reads the raw column, so a stored
     * JSON string arrives quoted. Decode when it parses, take the raw value
     * when it does not, and treat an empty string as absent.
     */
    protected function legacyString(\Illuminate\Support\Collection $legacy, string $key): ?string
    {
        $raw = $legacy->get($key);

        if ($raw === null) {
            return null;
        }

        $decoded = json_decode((string) $raw, true);
        $value   = (json_last_error() === JSON_ERROR_NONE && is_scalar($decoded)) ? (string) $decoded : (string) $raw;
        $value   = trim($value);

        return $value === '' ? null : $value;
    }

    public function down(): void
    {
        if (Schema::hasTable('storefront_orders') && Schema::hasColumn('storefront_orders', 'delivery_zone_id')) {
            Schema::table('storefront_orders', function (Blueprint $table) {
                $table->dropForeign(['delivery_zone_id']);
                $table->dropColumn(['delivery_zone_id', 'delivery_zone_name']);
            });
        }

        Schema::dropIfExists('storefront_delivery_zones');
        Schema::dropIfExists('storefront_settings');
    }
};
