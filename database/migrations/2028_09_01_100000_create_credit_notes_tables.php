<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('credit_notes')) {
            Schema::create('credit_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->unsignedBigInteger('company_id')->nullable();
                $table->string('credit_note_number', 40);
                $table->unsignedBigInteger('original_invoice_id')->nullable();
                $table->unsignedBigInteger('original_order_id')->nullable();
                $table->unsignedBigInteger('sales_return_id')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('currency_code', 3)->default('GHS');
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('tax_total', 15, 2)->default(0);
                $table->decimal('total', 15, 2)->default(0);
                $table->date('issue_date');
                $table->date('accounting_date');
                $table->text('reason')->nullable();
                $table->string('status', 30)->default('draft');
                $table->unsignedBigInteger('approved_by_user_id')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('posted_by_user_id')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->unsignedBigInteger('journal_batch_id')->nullable();
                $table->string('idempotency_key', 160);
                $table->unsignedBigInteger('offset_account_id')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->unsignedBigInteger('reversal_of_credit_note_id')->nullable();
                $table->unsignedBigInteger('reversed_by_credit_note_id')->nullable();
                $table->text('failure_reason')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
                $table->foreign('original_invoice_id')->references('id')->on('invoices')->nullOnDelete();
                $table->foreign('sales_return_id')->references('id')->on('sales_returns')->nullOnDelete();
                $table->foreign('customer_id')->references('id')->on('counterparties')->nullOnDelete();
                $table->foreign('journal_batch_id')->references('id')->on('journal_batches')->nullOnDelete();
                $table->foreign('offset_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();

                $table->unique(['organization_id', 'credit_note_number'], 'credit_notes_org_number_unique');
                $table->unique(['organization_id', 'idempotency_key'], 'credit_notes_org_idempotency_unique');
                $table->index(['organization_id', 'status', 'accounting_date'], 'credit_notes_status_date_idx');
                $table->index(['sales_return_id'], 'credit_notes_sales_return_idx');
            });
        }

        if (! Schema::hasTable('credit_note_lines')) {
            Schema::create('credit_note_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->unsignedBigInteger('credit_note_id');
                $table->unsignedBigInteger('original_invoice_line_id')->nullable();
                $table->unsignedBigInteger('original_order_item_id')->nullable();
                $table->unsignedBigInteger('sales_return_id')->nullable();
                $table->string('returnable_type', 120)->nullable();
                $table->unsignedBigInteger('returnable_id')->nullable();
                $table->string('description', 500);
                $table->decimal('quantity', 15, 4);
                $table->decimal('unit_price', 15, 4);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('line_total', 15, 2);
                $table->unsignedBigInteger('revenue_account_id')->nullable();
                $table->decimal('quantity_sold', 15, 4)->default(0);
                $table->decimal('quantity_previously_credited', 15, 4)->default(0);
                $table->timestamps();

                $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
                $table->foreign('credit_note_id')->references('id')->on('credit_notes')->cascadeOnDelete();
                $table->foreign('original_invoice_line_id')->references('id')->on('invoice_lines')->nullOnDelete();
                $table->foreign('sales_return_id')->references('id')->on('sales_returns')->nullOnDelete();
                $table->foreign('revenue_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();

                $table->index(['credit_note_id'], 'credit_note_lines_note_idx');
                $table->index(['returnable_type', 'returnable_id'], 'credit_note_lines_returnable_idx');
            });
        }

        Schema::table('sales_returns', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_returns', 'credit_note_id')) {
                $table->unsignedBigInteger('credit_note_id')->nullable()->after('revenue_reversal_batch_id');
                $table->foreign('credit_note_id')->references('id')->on('credit_notes')->nullOnDelete();
            }
            if (! Schema::hasColumn('sales_returns', 'credit_note_status')) {
                $table->string('credit_note_status', 30)->nullable()->after('credit_note_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            if (Schema::hasColumn('sales_returns', 'credit_note_id')) {
                $table->dropConstrainedForeignId('credit_note_id');
            }
            if (Schema::hasColumn('sales_returns', 'credit_note_status')) {
                $table->dropColumn('credit_note_status');
            }
        });

        Schema::dropIfExists('credit_note_lines');
        Schema::dropIfExists('credit_notes');
    }
};
