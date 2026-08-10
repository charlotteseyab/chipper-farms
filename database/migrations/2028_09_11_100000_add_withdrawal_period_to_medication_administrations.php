<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medicine withdrawal periods (Phase 11, blueprint §35).
 *
 * WHY THIS IS THE FIRST THING PHASE 11 BUILDS
 * -------------------------------------------
 * §35 step 2 requires the eligibility check to confirm a pig is "not under
 * withdrawal/health hold" before it can be pushed to the shop. The check could
 * not be written, because the data it needs did not exist:
 * `medication_administrations` records what was given and when, and nothing at
 * all about how long the animal must not enter the food chain afterwards.
 *
 * This is not a UX gap. Selling an animal inside its withdrawal period puts
 * drug residue into food, and in Ghana as elsewhere it is a regulatory matter
 * for the farm, not a customer-service one. A storefront that can list such an
 * animal with one click makes it likelier, not less.
 *
 * DAYS AS ENTERED, END DATE DERIVED AT WRITE
 * ------------------------------------------
 * `withdrawal_period_days` is what the person recording the treatment reads off
 * the bottle. `withdrawal_ends_on` is `administration_date + days`, computed
 * when the row is written.
 *
 * Storing both is deliberate. Deriving the end date on every read would mean
 * date arithmetic inside every eligibility query — and the query that matters
 * is "is any pig in this list under withdrawal", asked while a staff member
 * waits. A stored, indexed date answers it directly. The pair can only diverge
 * if someone edits one column by hand, and `MedicationAdministration` keeps
 * them in step on save.
 *
 * NULLABLE, BECAUSE MOST TREATMENTS HAVE NO WITHDRAWAL
 * ----------------------------------------------------
 * Vitamins, drenches and many routine treatments carry none. Null means "none
 * recorded", and the eligibility check treats null as no restriction rather
 * than guessing a default — inventing a withdrawal period would block sales
 * that are perfectly legal, and farms would learn to ignore the warning.
 *
 * What it does NOT do is treat null as "safe". §35's check reports separately
 * that a recent treatment carries no withdrawal information, so the gap is
 * visible rather than silently permissive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('medication_administrations')) {
            return;
        }

        Schema::table('medication_administrations', function (Blueprint $table) {
            if (! Schema::hasColumn('medication_administrations', 'withdrawal_period_days')) {
                // Unsigned small int: withdrawal periods are measured in days
                // and the longest in common veterinary use is a few months.
                $table->unsignedSmallInteger('withdrawal_period_days')->nullable()->after('route');
            }

            if (! Schema::hasColumn('medication_administrations', 'withdrawal_ends_on')) {
                $table->date('withdrawal_ends_on')->nullable()->after('withdrawal_period_days');
            }
        });

        // Indexed on the pair the eligibility check reads: "does this pig have
        // a withdrawal that has not finished".
        Schema::table('medication_administrations', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('medication_administrations'))->pluck('name');

            if (! $indexes->contains('medication_admin_withdrawal_idx')) {
                $table->index(['pig_id', 'withdrawal_ends_on'], 'medication_admin_withdrawal_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('medication_administrations')) {
            return;
        }

        Schema::table('medication_administrations', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('medication_administrations'))->pluck('name');

            if ($indexes->contains('medication_admin_withdrawal_idx')) {
                $table->dropIndex('medication_admin_withdrawal_idx');
            }

            foreach (['withdrawal_ends_on', 'withdrawal_period_days'] as $column) {
                if (Schema::hasColumn('medication_administrations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
