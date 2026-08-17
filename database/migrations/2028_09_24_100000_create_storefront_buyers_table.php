<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('storefront_buyers')) {
            Schema::create('storefront_buyers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->nullable()->index();
                $table->unsignedBigInteger('counterparty_id')->nullable()->index();
                $table->string('name', 200);
                $table->string('email', 190);
                $table->string('phone', 60)->nullable();
                $table->string('password');
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['organization_id', 'email'], 'storefront_buyers_org_email_unique');
                $table->foreign('counterparty_id')
                    ->references('id')->on('counterparties')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('storefront_orders') && ! Schema::hasColumn('storefront_orders', 'storefront_buyer_id')) {
            Schema::table('storefront_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('storefront_buyer_id')->nullable()->after('customer_user_id')->index();
                $table->foreign('storefront_buyer_id', 'storefront_orders_buyer_fk')
                    ->references('id')->on('storefront_buyers')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('storefront_orders') && Schema::hasColumn('storefront_orders', 'storefront_buyer_id')) {
            Schema::table('storefront_orders', function (Blueprint $table) {
                try {
                    $table->dropForeign('storefront_orders_buyer_fk');
                } catch (\Throwable) {
                }

                $table->dropColumn('storefront_buyer_id');
            });
        }

        Schema::dropIfExists('storefront_buyers');
    }
};
