<?php

// Narrow repair for legacy sale backfills whose date-only midnight release
// predates registration. No printed numbers or ownership are changed.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) { fwrite(STDERR, $e->getMessage().PHP_EOL); exit(1); });

use App\Models\EarTagAssignment;
use App\Services\Audit\AuditTrailService;
use Illuminate\Support\Facades\DB;

$apply = in_array('--apply', $argv, true);
DB::transaction(function () use ($apply) {
    $repairs = [];
    $rows = DB::table('ear_tag_assignments')->whereColumn('released_at', '<', 'assigned_at')->orderBy('id')->lockForUpdate()->get();
    foreach ($rows as $row) {
        $day = substr($row->assigned_at, 0, 10);
        if ($row->release_reason !== 'sale' || $row->is_current !== null
            || $row->released_at !== $day.' 00:00:00'
            || ! str_contains(strtolower($row->notes ?? ''), 'backfilled')) {
            throw new RuntimeException("Assignment {$row->id} requires manual date reconciliation.");
        }
        $sales = DB::table('pig_sales')->where('organization_id', $row->organization_id)->where('pig_id', $row->pig_id)
            ->whereNull('deleted_at')->whereDate('sale_date', $day)->lockForUpdate()->get();
        if ($sales->count() !== 1 || substr($sales[0]->created_at, 0, 10) !== $day || $sales[0]->created_at < $row->assigned_at) {
            throw new RuntimeException("Assignment {$row->id} has no unambiguous same-day sale recording timestamp.");
        }
        $release = $sales[0]->created_at;
        $overlap = DB::table('ear_tag_assignments')->where('id', '!=', $row->id)->where('organization_id', $row->organization_id)
            ->where(fn ($q) => $q->where('pig_id', $row->pig_id)->orWhere('ear_tag_id', $row->ear_tag_id))
            ->where('assigned_at', '<', $release)->where(fn ($q) => $q->whereNull('released_at')->orWhere('released_at', '>', $row->assigned_at))->exists();
        if ($overlap) throw new RuntimeException("Assignment {$row->id} would overlap another assignment.");
        $repairs[] = ['before' => (array) $row, 'released_at' => $release, 'sale_id' => $sales[0]->id];
    }
    echo json_encode(array_map(fn ($repair) => ['assignment_id' => $repair['before']['id'], 'old_release' => $repair['before']['released_at'], 'new_release' => $repair['released_at'], 'sale_id' => $repair['sale_id']], $repairs), JSON_PRETTY_PRINT).PHP_EOL;
    if (! $apply || $repairs === []) { echo "No writes made.\n"; return; }
    $dir = sys_get_temp_dir().'/tag-date-repair-'.bin2hex(random_bytes(6));
    if (! mkdir($dir, 0700) || file_put_contents($dir.'/before.json', json_encode($repairs, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
        throw new RuntimeException('Unable to save pre-repair rows; no changes committed.');
    }
    foreach ($repairs as $repair) {
        $reason = 'Legacy date-only sale backfill set release to midnight before assignment. Use the existing same-day sale recording timestamp (sale #'.$repair['sale_id'].'); this is a recorded-event convention, not a claimed physical recovery time. Printed identity and ownership unchanged.';
        $after = ['released_at' => $repair['released_at'], 'correction_reason' => $reason, 'updated_at' => now()->toDateTimeString()];
        DB::table('ear_tag_assignments')->where('id', $repair['before']['id'])->update($after);
        if (! app(AuditTrailService::class)->log('stock', 'ear_tag_corrected', [
            'organization_id' => $repair['before']['organization_id'], 'name' => 'CLI migration maintenance',
            'auditable_type' => EarTagAssignment::class, 'auditable_id' => $repair['before']['id'],
            'old_values' => $repair['before'], 'new_values' => array_merge($repair['before'], $after),
            'description' => $reason, 'metadata' => ['repair' => basename(__FILE__), 'source_sale_id' => $repair['sale_id'], 'backup' => $dir.'/before.json'],
        ])) throw new RuntimeException('Audit failed; all repairs rolled back.');
    }
    echo count($repairs)." date corrections committed with audit records. Before-values: {$dir}/before.json\n";
});
