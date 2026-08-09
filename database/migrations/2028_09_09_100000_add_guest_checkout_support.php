<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guest cart and checkout (Phase 9, blueprint §38).
 *
 * WHAT THIS UNBLOCKS
 * ------------------
 * §17 lists "guest cart/checkout — buyer can order without creating account
 * first" as *essential* storefront scope, and §38 requires the cart to work
 * "before login through session/cart token". Today `storefront_carts.user_id`
 * is NOT NULL and every cart and checkout route sits behind `auth:sanctum`, so
 * a buyer has to register before they can put anything in a basket. For a farm
 * shop that is the difference between an order and a bounce.
 *
 * THE CART GETS A TOKEN, NOT A SESSION KEY
 * ----------------------------------------
 * The cart stays in the database and is addressed by an opaque random token
 * held in a cookie. Keeping the cart itself in the session would lose it the
 * moment the session rotated — which Laravel does on login, exactly when a
 * buyer is most likely to have a full basket. A row plus a token also means the
 * cart survives a browser restart, and that the same merge code path handles
 * "logged in halfway through" as handles "came back tomorrow".
 *
 * WHY A CLAIM TOKEN AND NOT AN EMAIL MATCH
 * ----------------------------------------
 * §38 asks for "optional account creation after order". The obvious
 * implementation — attach guest orders to whoever later registers with the same
 * email — makes someone else's order history a prize for registering with their
 * address. Email ownership is not proven at registration in this application.
 *
 * So each guest order carries a single-use `guest_claim_token`, delivered in
 * the confirmation URL the buyer already holds. Claiming an order requires
 * presenting it, which proves the claimant is the person who placed it. The
 * token is cleared the moment it is used, so a forwarded link cannot be
 * replayed against a second account.
 *
 * The same token is what lets a guest track their order without an account at
 * all — the recovery requirement in §38's last row.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // Carts: a cart may belong to a user OR to a guest token.
        // ---------------------------------------------------------------
        if (Schema::hasTable('storefront_carts')) {
            if (! Schema::hasColumn('storefront_carts', 'guest_token')) {
                Schema::table('storefront_carts', function (Blueprint $table) {
                    // 64 hex characters of random. Long enough that guessing a
                    // stranger's cart is not a strategy, and opaque so it
                    // carries no information about the buyer.
                    $table->string('guest_token', 64)->nullable()->after('user_id');
                    $table->index(['guest_token', 'status'], 'storefront_carts_guest_status_idx');
                });
            }

            // user_id becomes nullable. Done with raw SQL per driver rather
            // than doctrine/dbal change(), which this project does not carry.
            $this->makeCartUserIdNullable();
        }

        // ---------------------------------------------------------------
        // Orders: who the guest was, and how they prove the order is theirs.
        // ---------------------------------------------------------------
        if (Schema::hasTable('storefront_orders') && ! Schema::hasColumn('storefront_orders', 'guest_claim_token')) {
            Schema::table('storefront_orders', function (Blueprint $table) {
                // Contact details as given at checkout, snapshotted onto the
                // order. The counterparty record carries them too, but a
                // counterparty can be edited or merged later and the order has
                // to keep saying who placed it and how they were reachable.
                $table->string('guest_name', 200)->nullable()->after('customer_user_id');
                $table->string('guest_email', 190)->nullable()->after('guest_name');
                $table->string('guest_phone', 60)->nullable()->after('guest_email');

                // Single-use. Cleared on claim so a forwarded confirmation link
                // cannot be replayed against a second account.
                $table->string('guest_claim_token', 64)->nullable()->after('guest_phone');
                $table->timestamp('claimed_at')->nullable()->after('guest_claim_token');

                $table->unique('guest_claim_token', 'storefront_orders_claim_token_unique');
            });
        }
    }

    /**
     * `storefront_carts.user_id` was NOT NULL. A guest cart has no user.
     *
     * Widening a column cannot reject existing rows, so this is safe to run on
     * a populated table — the direction that matters. SQLite cannot alter a
     * column in place, so the table is rebuilt there; MySQL/MariaDB and
     * PostgreSQL take a straight ALTER.
     */
    protected function makeCartUserIdNullable(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        match ($driver) {
            'mysql', 'mariadb' => DB::statement(
                'ALTER TABLE storefront_carts MODIFY user_id BIGINT UNSIGNED NULL'
            ),
            'pgsql' => DB::statement(
                'ALTER TABLE storefront_carts ALTER COLUMN user_id DROP NOT NULL'
            ),
            // SQLite: a fresh install builds the column from this migration's
            // predecessor, so rebuilding is only needed where the table already
            // exists with the constraint. Laravel's SQLite grammar supports
            // this via a table rebuild.
            'sqlite' => $this->rebuildSqliteCarts(),
            default  => null,
        };
    }

    protected function rebuildSqliteCarts(): void
    {
        // SQLite has no ALTER COLUMN. Rebuild the table with a nullable
        // user_id, copying every row. Wrapped so a partially-applied run
        // leaves the original in place rather than a half-built replacement.
        DB::transaction(function () {
            Schema::create('storefront_carts_rebuild', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('guest_token', 64)->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->index(['user_id', 'status'], 'storefront_carts_rebuild_user_status_idx');
                $table->index(['guest_token', 'status'], 'storefront_carts_rebuild_guest_status_idx');
            });

            DB::statement(
                'INSERT INTO storefront_carts_rebuild (id, organization_id, user_id, guest_token, status, created_at, updated_at)
                 SELECT id, organization_id, user_id, guest_token, status, created_at, updated_at FROM storefront_carts'
            );

            Schema::drop('storefront_carts');
            Schema::rename('storefront_carts_rebuild', 'storefront_carts');

            // SQLite index names are global, so the rebuild had to use
            // temporary ones while both tables existed. Dropping the original
            // table freed the real names; restore them, or `down()` and any
            // later migration would be looking for indexes that do not exist
            // under the names the rest of the schema uses.
            Schema::table('storefront_carts', function (Blueprint $table) {
                $table->dropIndex('storefront_carts_rebuild_user_status_idx');
                $table->dropIndex('storefront_carts_rebuild_guest_status_idx');
                $table->dropIndex('storefront_carts_rebuild_organization_id_index');
                $table->dropIndex('storefront_carts_rebuild_user_id_index');

                $table->index('organization_id', 'storefront_carts_organization_id_index');
                $table->index('user_id', 'storefront_carts_user_id_index');
                $table->index(['user_id', 'status'], 'storefront_carts_user_status_idx');
                $table->index(['guest_token', 'status'], 'storefront_carts_guest_status_idx');
            });
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('storefront_orders') && Schema::hasColumn('storefront_orders', 'guest_claim_token')) {
            Schema::table('storefront_orders', function (Blueprint $table) {
                $table->dropUnique('storefront_orders_claim_token_unique');
                $table->dropColumn([
                    'guest_name', 'guest_email', 'guest_phone', 'guest_claim_token', 'claimed_at',
                ]);
            });
        }

        if (Schema::hasTable('storefront_carts') && Schema::hasColumn('storefront_carts', 'guest_token')) {
            Schema::table('storefront_carts', function (Blueprint $table) {
                $table->dropIndex('storefront_carts_guest_status_idx');
                $table->dropColumn('guest_token');
            });
        }

        // user_id is deliberately left nullable. Narrowing it again would
        // reject any guest cart written while this migration was applied —
        // the exact failure mode the programme's migration rule warns about.
    }
};
