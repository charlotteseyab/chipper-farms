<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pig_sales', function (Blueprint $table) {
            $table->string('destination')->nullable()->after('buyer_contact'); // Where pig is going
            $table->enum('sale_method', ['auction', 'direct', 'contract', 'other'])->default('direct')->after('destination');
            $table->text('transport_details')->nullable()->after('sale_method'); // How pig was transported
            $table->decimal('final_weight', 8, 2)->nullable()->after('transport_details'); // Weight at time of sale
            $table->text('sale_notes')->nullable()->after('final_weight'); // Additional sale information
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pig_sales', function (Blueprint $table) {
            $table->dropColumn([
                'destination',
                'sale_method',
                'transport_details',
                'final_weight',
                'sale_notes'
            ]);
        });
    }
};