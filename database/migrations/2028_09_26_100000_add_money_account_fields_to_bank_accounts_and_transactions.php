<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_accounts', 'account_kind')) {
                $table->string('account_kind', 20)->default('bank')->after('account_id');
            }
            if (! Schema::hasColumn('bank_accounts', 'provider')) {
                $table->string('provider', 80)->nullable()->after('bank_name');
            }
            if (! Schema::hasColumn('bank_accounts', 'account_name')) {
                $table->string('account_name', 120)->nullable()->after('provider');
            }
            if (! Schema::hasColumn('bank_accounts', 'wallet_number')) {
                $table->string('wallet_number', 40)->nullable()->after('account_number');
            }
            if (! Schema::hasColumn('bank_accounts', 'storefront_payment_method')) {
                $table->string('storefront_payment_method', 40)->nullable()->after('default_csv_format');
            }
        });

        Schema::table('quick_expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('quick_expenses', 'money_account_id')) {
                $table->unsignedBigInteger('money_account_id')->nullable()->after('payment_method_key');
                $table->index('money_account_id', 'quick_expenses_money_account_idx');
            }
        });

        Schema::table('simple_purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('simple_purchases', 'money_account_id')) {
                $table->unsignedBigInteger('money_account_id')->nullable()->after('payment_method_key');
                $table->index('money_account_id', 'simple_purchases_money_account_idx');
            }
        });

        Schema::table('receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('receipts', 'source_account_id')) {
                $table->unsignedBigInteger('source_account_id')->nullable()->after('payment_method_id');
                $table->index('source_account_id', 'receipts_source_account_idx');
            }
        });

        $this->backfillPaystackAccounts();
        $this->backfillPaystackPaymentMethods();
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            if (Schema::hasColumn('receipts', 'source_account_id')) {
                $table->dropIndex('receipts_source_account_idx');
                $table->dropColumn('source_account_id');
            }
        });

        Schema::table('simple_purchases', function (Blueprint $table) {
            if (Schema::hasColumn('simple_purchases', 'money_account_id')) {
                $table->dropIndex('simple_purchases_money_account_idx');
                $table->dropColumn('money_account_id');
            }
        });

        Schema::table('quick_expenses', function (Blueprint $table) {
            if (Schema::hasColumn('quick_expenses', 'money_account_id')) {
                $table->dropIndex('quick_expenses_money_account_idx');
                $table->dropColumn('money_account_id');
            }
        });

        Schema::table('bank_accounts', function (Blueprint $table) {
            foreach ([
                'storefront_payment_method',
                'wallet_number',
                'account_name',
                'provider',
                'account_kind',
            ] as $column) {
                if (Schema::hasColumn('bank_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function backfillPaystackAccounts(): void
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            return;
        }

        $organizationIds = \DB::table('chart_of_accounts')
            ->whereNotNull('organization_id')
            ->distinct()
            ->pluck('organization_id');

        if ($organizationIds->isEmpty() && Schema::hasTable('organizations')) {
            $organizationIds = \DB::table('organizations')->pluck('id');
        }

        foreach ($organizationIds as $organizationId) {
            $exists = \DB::table('chart_of_accounts')
                ->where('organization_id', $organizationId)
                ->where('code', '1070')
                ->exists();

            if ($exists) {
                continue;
            }

            $parentId = \DB::table('chart_of_accounts')
                ->where('organization_id', $organizationId)
                ->where('code', '1000')
                ->value('id');

            \DB::table('chart_of_accounts')->insert([
                'organization_id'        => $organizationId,
                'code'                   => '1070',
                'name'                   => 'Paystack Clearing',
                'description'            => 'Online card/mobile checkout receipts awaiting settlement',
                'account_type'           => 'asset',
                'account_subtype'        => 'current_asset',
                'parent_id'              => $parentId,
                'currency_code'          => 'GHS',
                'is_control'             => false,
                'is_active'              => true,
                'is_system'              => false,
                'allow_manual_journal'   => true,
                'display_order'          => 1070,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);
        }
    }

    private function backfillPaystackPaymentMethods(): void
    {
        if (! Schema::hasTable('payment_methods')) {
            return;
        }

        $organizationIds = Schema::hasTable('organizations')
            ? \DB::table('organizations')->pluck('id')
            : collect();

        if ($organizationIds->isEmpty()) {
            $organizationIds = collect([null]);
        }

        foreach ($organizationIds as $organizationId) {
            \DB::table('payment_methods')->updateOrInsert(
                [
                    'organization_id' => $organizationId,
                    'code'            => 'PAYSTACK',
                ],
                [
                    'name'               => 'Paystack Online Checkout',
                    'description'        => 'Online storefront checkout through Paystack.',
                    'is_cash'            => false,
                    'is_bank'            => true,
                    'is_mobile_money'    => false,
                    'is_credit'          => false,
                    'requires_reference' => true,
                    'gl_account_hint'    => 'PAYSTACK_CLEARING',
                    'display_order'      => 33,
                    'is_active'          => true,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ],
            );
        }
    }
};
