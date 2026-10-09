<?php

namespace App\Console\Commands;

use App\Models\Finance\InventoryBalance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off alignment of raw_materials.quantity_in_stock with the stock ledger.
 *
 * New movements keep the two in step automatically (InventoryService mirrors
 * the ledger). Rows written before that may have drifted. Dry run by default.
 * Materials with stock on the old column but no ledger history are listed and
 * left alone: they need an opening balance, not a silent zero.
 */
class SyncRawMaterialStock extends Command
{
    protected $signature = 'inventory:sync-raw-material-stock {--apply : Write the ledger quantities to raw_materials}';

    protected $description = 'Compare raw material stock with the stock ledger and optionally align it';

    public function handle(): int
    {
        $balances = InventoryBalance::withoutGlobalScopes()
            ->where('item_kind', InventoryBalance::ITEM_KIND_RAW_MATERIAL)
            ->get()
            ->keyBy(fn (InventoryBalance $b) => $b->organization_id.'-'.$b->item_id);

        $drifted = [];
        $unledgered = [];
        foreach (DB::table('raw_materials')->orderBy('organization_id')->orderBy('id')->get() as $material) {
            $balance = $balances->get($material->organization_id.'-'.$material->id);
            $column = round((float) $material->quantity_in_stock, 2);
            if ($balance === null) {
                if ($column != 0.0) {
                    $unledgered[] = [$material->organization_id, $material->id, $material->name, $column];
                }

                continue;
            }
            $ledger = round((float) $balance->on_hand_qty, 2);
            if ($ledger !== $column) {
                $drifted[] = [$material->organization_id, $material->id, $material->name, $column, $ledger, (float) $balance->on_hand_qty];
            }
        }

        if ($drifted === [] && $unledgered === []) {
            $this->info('Raw material stock already matches the ledger.');

            return self::SUCCESS;
        }

        if ($drifted !== []) {
            $this->table(['Org', 'ID', 'Material', 'Old column', 'Ledger'], array_map(fn ($r) => array_slice($r, 0, 5), $drifted));
        }
        if ($unledgered !== []) {
            $this->warn('Stock on the old column with no ledger history (record an opening balance for these; not changed):');
            $this->table(['Org', 'ID', 'Material', 'Old column'], $unledgered);
        }

        if (! $this->option('apply')) {
            $this->line('Dry run. Re-run with --apply to align '.count($drifted).' material(s) to the ledger.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($drifted) {
            foreach ($drifted as [$orgId, $id, , , , $ledgerQty]) {
                DB::table('raw_materials')->where('organization_id', $orgId)->where('id', $id)->update(['quantity_in_stock' => $ledgerQty]);
            }
        });
        $this->info('Aligned '.count($drifted).' material(s) to the ledger.');

        return self::SUCCESS;
    }
}
