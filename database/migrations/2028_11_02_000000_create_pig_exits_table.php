<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Animals that leave the herd other than by sale or death: transferred
        // to another farm or owner, or culled. One exit per pig; the pig row is
        // soft-deleted like a sale or death and its tag released.
        Schema::create('pig_exits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('pig_id')->constrained('pigs_management')->restrictOnDelete();
            $table->string('exit_type', 20);            // transfer_out | cull
            $table->date('exit_date');
            $table->string('reason', 40);               // fixed list per exit type
            $table->string('reason_other')->nullable();
            $table->string('destination')->nullable();  // transfer: receiving farm or owner
            $table->string('disposal', 40)->nullable(); // cull: what happened to the animal
            $table->decimal('final_weight', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('tag_outcome', 30);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('pig_id', 'pig_exits_one_per_pig');
            $table->index(['organization_id', 'exit_date']);
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('pig_exits')->exists()) {
            throw new RuntimeException('Pig exits exist; rollback would leave those animals removed with no record of why.');
        }
        Schema::dropIfExists('pig_exits');
    }
};
