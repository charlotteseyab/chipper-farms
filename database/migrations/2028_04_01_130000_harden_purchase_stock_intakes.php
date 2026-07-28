<?php

use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_stock_intakes')) {
            Schema::table('purchase_stock_intakes', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_stock_intakes', 'organization_id')) {
                    $table->unsignedBigInteger('organization_id')->nullable()->after('id');
                    $table->index('organization_id', 'purchase_stock_intakes_org_idx');
                }

                if (! Schema::hasColumn('purchase_stock_intakes', 'finance_posting_status')) {
                    $table->string('finance_posting_status', 30)->default('not_configured')->after('acquisition_journal_batch_id');
                }

                if (! Schema::hasColumn('purchase_stock_intakes', 'finance_posting_message')) {
                    $table->text('finance_posting_message')->nullable()->after('finance_posting_status');
                }
            });

            $defaultOrganizationId = Organization::query()->orderBy('id')->value('id');
            if ($defaultOrganizationId) {
                DB::table('purchase_stock_intakes')
                    ->whereNull('organization_id')
                    ->update(['organization_id' => $defaultOrganizationId]);
            }
        }

        if (! Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->nullable();
                $table->string('movement_type', 40);
                $table->string('source_type', 80);
                $table->unsignedBigInteger('source_id');
                $table->string('animal_category', 40);
                $table->integer('quantity');
                $table->timestamp('movement_date');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reference', 120)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['source_type', 'source_id'], 'stock_movements_source_unique');
                $table->index(['organization_id', 'animal_category', 'movement_type'], 'stock_movements_org_category_type_idx');
                $table->index('movement_date', 'stock_movements_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');

        if (Schema::hasTable('purchase_stock_intakes')) {
            Schema::table('purchase_stock_intakes', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_stock_intakes', 'finance_posting_message')) {
                    $table->dropColumn('finance_posting_message');
                }

                if (Schema::hasColumn('purchase_stock_intakes', 'finance_posting_status')) {
                    $table->dropColumn('finance_posting_status');
                }

                if (Schema::hasColumn('purchase_stock_intakes', 'organization_id')) {
                    $table->dropIndex('purchase_stock_intakes_org_idx');
                    $table->dropColumn('organization_id');
                }
            });
        }
    }
};
