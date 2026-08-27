<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('purchase_requests')
            ->whereIn('status', ['approved', 'paid'])
            ->orderBy('id')
            ->chunkById(100, function ($requests): void {
                foreach ($requests as $request) {
                    $paid = (float) DB::table('payments')
                        ->where('purchase_request_id', $request->id)
                        ->where('status', 'posted')
                        ->sum('amount');
                    $total = (float) $request->total_amount;
                    $progress = $paid <= 0
                        ? 'not_paid'
                        : ($paid + 0.005 >= $total ? 'paid' : 'partially_paid');

                    DB::table('purchase_requests')->where('id', $request->id)->update([
                        'status' => $progress === 'paid' ? 'paid' : 'approved',
                        'payment_progress' => $progress,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Payment-derived statuses cannot be safely restored to legacy values.
    }
};
