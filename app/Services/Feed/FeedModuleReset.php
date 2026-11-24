<?php

namespace App\Services\Feed;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Removes one farm's feed records and everything they posted, so the module
 * can start again from clean walkthrough data.
 *
 * WHAT GOES
 * ---------
 * Feed setup and activity: raw materials and their stock logs, feed types,
 * recipes, rations, stage overrides, batches, production plans, feedings,
 * stock counts, feed reminders. Feed purchasing: purchase requests whose
 * every line is a raw material, with their vendor invoices, payments and
 * allocations. And the accounting all of that created: the journal batches,
 * journals, lines and postings, the raw-material and finished-feed stock
 * ledger, the feed cost carried in the herd's stage values, and the domain
 * events behind them.
 *
 * WHAT STAYS
 * ----------
 * Pigs, health, medicine stock, sales, the storefront, other purchases, other
 * farms, the feeding-stage system groups and the farm's feed settings (bag
 * weights, wastage, alert thresholds).
 *
 * It refuses rather than guesses: if a journal batch it would remove is also
 * referenced by a customer invoice, receipt, refund or credit note, or the
 * farm has feed sales, mixed purchases referencing its raw materials, or
 * later group cost movements depending on feed costs, nothing is deleted.
 *
 * Every row removed is returned, so the caller can keep a backup.
 */
class FeedModuleReset
{
    /** @return array<string, list<array>> the rows that would be (or were) removed, by table */
    public function run(int $organizationId, bool $dryRun = true): array
    {
        $rows = DB::transaction(function () use ($organizationId, $dryRun) {
            DB::table('organizations')->where('id', $organizationId)->lockForUpdate()->first();
            $rows = $this->collect($organizationId);
            if (! $dryRun) {
                $this->delete($organizationId, $rows);
            }

            return $rows;
        }, 3);

        return array_map(fn (Collection $c) => $c->map(fn ($r) => (array) $r)->values()->all(), $rows);
    }

    /** @return array<string, Collection> */
    private function collect(int $org): array
    {
        $t = fn (string $table) => DB::table($table)->where('organization_id', $org);
        $in = fn (string $table, string $column, Collection $ids) => $ids->isEmpty() ? collect() : DB::table($table)->whereIn($column, $ids->unique()->values()->all())->get();

        if ($t('feed_sales')->exists()) {
            throw new \RuntimeException('This farm has feed sales. They post to customer invoices and revenue; reset does not handle them.');
        }

        $r = [];
        $r['raw_materials'] = $t('raw_materials')->get();
        $r['feed_types'] = $t('feed_types')->get();
        $r['feed_type_recipes'] = $in('feed_type_recipes', 'feed_type_id', $r['feed_types']->pluck('id'));
        $r['feed_recipe_versions'] = $t('feed_recipe_versions')->get();
        $r['feeding_standards'] = $t('feeding_standards')->get();
        $r['feed_stage_overrides'] = $t('feed_stage_overrides')->get();
        $r['feed_batches'] = $t('feed_batches')->get();
        $r['feed_batch_items'] = $in('feed_batch_items', 'feed_batch_id', $r['feed_batches']->pluck('id'));
        $r['feed_production_plans'] = $t('feed_production_plans')->get();
        $r['feed_issues'] = $t('feed_issues')->get();
        $r['feed_reminders'] = $t('feed_reminders')->get();
        $r['raw_material_stock_logs'] = $t('raw_material_stock_logs')->get();
        $r['stock_counts'] = $t('stock_counts')->get();
        $r['stock_count_lines'] = $in('stock_count_lines', 'stock_count_id', $r['stock_counts']->pluck('id'));

        // Feed purchasing: requests made up entirely of raw materials.
        $requests = $t('purchase_requests')->get();
        $items = $in('purchase_request_items', 'purchase_request_id', $requests->pluck('id'));
        $feedRequestIds = $requests->pluck('id')->filter(fn ($id) => ($lines = $items->where('purchase_request_id', $id))->isNotEmpty()
            && $lines->every(fn ($line) => $line->raw_material_id !== null))->values();
        if ($items->whereNotIn('purchase_request_id', $feedRequestIds)
            ->whereIn('raw_material_id', $r['raw_materials']->pluck('id'))->isNotEmpty()) {
            throw new \RuntimeException('A mixed purchase request still references feed raw materials; nothing was deleted. Resolve that request before resetting feed.');
        }
        $r['purchase_requests'] = $requests->whereIn('id', $feedRequestIds)->values();
        $r['purchase_request_items'] = $items->whereIn('purchase_request_id', $feedRequestIds)->values();
        $r['vendor_invoices'] = $in('vendor_invoices', 'purchase_request_id', $feedRequestIds);
        $r['vendor_invoice_lines'] = $in('vendor_invoice_lines', 'vendor_invoice_id', $r['vendor_invoices']->pluck('id'));
        $r['payments'] = $in('payments', 'purchase_request_id', $feedRequestIds);
        $r['payment_allocations'] = DB::table('payment_allocations')->where('organization_id', $org)
            ->where(fn ($q) => $q->whereIn('vendor_invoice_id', $r['vendor_invoices']->pluck('id')->all() ?: [0])
                ->orWhereIn('payment_id', $r['payments']->pluck('id')->all() ?: [0]))->get();
        $r['payment_run_lines'] = DB::table('payment_run_lines')->where('organization_id', $org)
            ->where(fn ($q) => $q->whereIn('vendor_invoice_id', $r['vendor_invoices']->pluck('id')->all() ?: [0])
                ->orWhereIn('payment_id', $r['payments']->pluck('id')->all() ?: [0])
                ->orWhereIn('payment_allocation_id', $r['payment_allocations']->pluck('id')->all() ?: [0]))->get();
        $r['gr_ir_entries'] = DB::table('gr_ir_entries')->where('organization_id', $org)
            ->where(fn ($q) => $q->whereIn('raw_material_id', $r['raw_materials']->pluck('id')->all() ?: [0])
                ->orWhereIn('purchase_request_item_id', $r['purchase_request_items']->pluck('id')->all() ?: [0]))->get();

        $r['inventory_movements'] = $t('inventory_movements')->whereIn('item_kind', ['raw_material', 'finished_feed'])->get();
        $r['inventory_balances'] = $t('inventory_balances')->whereIn('item_kind', ['raw_material', 'finished_feed'])->get();

        $sources = [
            'App\\Models\\FeedIssue' => $r['feed_issues']->pluck('id'), 'App\\Models\\FeedBatch' => $r['feed_batches']->pluck('id'),
            'App\\Models\\RawMaterialStockLog' => $r['raw_material_stock_logs']->pluck('id'), 'App\\Models\\PurchaseRequest' => $feedRequestIds,
            'App\\Models\\Finance\\VendorInvoice' => $r['vendor_invoices']->pluck('id'), 'App\\Models\\Finance\\Payment' => $r['payments']->pluck('id'),
            'App\\Models\\Finance\\PaymentAllocation' => $r['payment_allocations']->pluck('id'),
            'App\\Models\\StockCountLine' => $r['stock_count_lines']->pluck('id'),
        ];
        $r['domain_events'] = $t('domain_events')->where(function ($q) use ($sources) {
            $q->whereRaw('1 = 0');
            foreach ($sources as $type => $sourceIds) {
                if ($sourceIds->isNotEmpty()) {
                    $q->orWhere(fn ($w) => $w->where('source_type', $type)->whereIn('source_id', $sourceIds->all()));
                }
            }
        })->get();

        // Every journal batch any of the above created.
        $batchIds = collect()
            ->merge($r['feed_issues']->pluck('journal_batch_id'))->merge($r['feed_issues']->pluck('reversal_journal_batch_id'))
            ->merge($r['feed_batches']->pluck('journal_batch_id'))->merge($r['feed_batches']->pluck('reversal_journal_batch_id'))
            ->merge($r['raw_material_stock_logs']->pluck('journal_batch_id'))->merge($r['inventory_movements']->pluck('journal_batch_id'))
            ->merge($r['stock_count_lines']->pluck('journal_batch_id'))
            ->merge($r['vendor_invoices']->pluck('journal_batch_id'))->merge($r['vendor_invoices']->pluck('reversed_by_journal_batch_id'))
            ->merge($r['payments']->pluck('journal_batch_id'))->merge($r['payments']->pluck('reversed_by_journal_batch_id'))
            ->merge($r['payment_allocations']->pluck('withholding_journal_batch_id'))->merge($r['payment_allocations']->pluck('reversed_by_journal_batch_id'))
            ->merge($r['gr_ir_entries']->pluck('receipt_journal_batch_id'))->merge($r['gr_ir_entries']->pluck('clearing_journal_batch_id'));
        $bySource = $t('journal_batches')->where(function ($q) use ($sources, $r) {
            $q->whereIn('domain_event_id', $r['domain_events']->pluck('id')->all() ?: [0]);
            foreach ($sources as $type => $ids) {
                if ($ids->isNotEmpty()) {
                    $q->orWhere(fn ($w) => $w->where('source_type', $type)->whereIn('source_id', $ids->all()));
                }
            }
        })->pluck('id');
        $batchIds = $batchIds->merge($bySource)->filter()->map(fn ($id) => (int) $id)->unique();
        // Reversals of those batches go with them.
        $batchIds = $batchIds->merge($t('journal_batches')->whereIn('reverses_batch_id', $batchIds->all() ?: [0])->pluck('id'))->unique()->values();

        foreach (['invoices' => ['journal_batch_id', 'reversed_by_journal_batch_id'], 'receipts' => ['journal_batch_id', 'reversed_by_journal_batch_id'],
            'refunds' => ['journal_batch_id', 'reversed_by_journal_batch_id'], 'credit_notes' => ['journal_batch_id']] as $table => $columns) {
            foreach ($columns as $column) {
                if ($batchIds->isNotEmpty() && DB::table($table)->whereIn($column, $batchIds->all())->exists()) {
                    throw new \RuntimeException("A journal batch the feed module posted is also used by {$table}; nothing was deleted.");
                }
            }
        }

        $r['journal_batches'] = $in('journal_batches', 'id', $batchIds);
        $r['journals'] = $in('journals', 'journal_batch_id', $batchIds);
        $r['journal_lines'] = $in('journal_lines', 'journal_id', $r['journals']->pluck('id'));
        $r['journal_postings'] = DB::table('journal_postings')->where('organization_id', $org)
            ->where(fn ($q) => $q->whereIn('journal_batch_id', $batchIds->all() ?: [0])->orWhereIn('domain_event_id', $r['domain_events']->pluck('id')->all() ?: [0]))->get();

        // Feed cost capitalised into the herd's stage values.
        $r['biological_asset_stage_transfers'] = $t('biological_asset_stage_transfers')
            ->where(fn ($q) => $q->whereIn('journal_batch_id', $batchIds->all() ?: [0])->orWhereIn('source_domain_event_id', $r['domain_events']->pluck('id')->all() ?: [0]))->get();
        // Only the stage balances that feed cost went into (they are adjusted, not wiped).
        $touched = $r['biological_asset_stage_transfers']->flatMap(fn ($m) => array_filter([
            $m->to_stage_key ? $m->group_id.'|'.$m->to_stage_key : null, $m->from_stage_key ? $m->group_id.'|'.$m->from_stage_key : null,
        ]))->unique();
        $r['biological_asset_stage_balances'] = $t('biological_asset_stage_balances')->get()
            ->filter(fn ($b) => $touched->contains($b->group_id.'|'.$b->stage_key))->values();

        $r['group_cost_movements'] = $t('group_cost_movements')
            ->whereIn('source_type', ['feed_issue', 'feed_issue_reversal'])
            ->whereIn('source_id', $r['feed_issues']->pluck('id'))->orderBy('id')->get();
        $r['group_cost_pools'] = $in('group_cost_pools', 'id', $r['group_cost_movements']->pluck('balance_id'));
        foreach ($r['group_cost_pools'] as $pool) {
            $first = $r['group_cost_movements']->where('balance_id', $pool->id)->first();
            if ($t('group_cost_movements')->where('balance_id', $pool->id)->where('id', '>', $first->id)
                ->whereNotIn('id', $r['group_cost_movements']->pluck('id'))->exists()) {
                throw new \RuntimeException('Later group cost activity depends on feed costs; nothing was deleted. Reverse the dependent activity before resetting feed.');
            }
        }

        return $r;
    }

    private function delete(int $org, array $r): void
    {
        $ids = fn (string $table) => $r[$table]->pluck('id')->all();
        $gone = fn (string $table) => $r[$table]->isEmpty() ? null : DB::table($table)->whereIn('id', $ids($table))->delete();

        // Feed movements are a verified tail of each pool's history. Restore
        // the snapshot before that tail, leaving earlier livestock costs intact.
        foreach ($r['group_cost_pools'] as $pool) {
            $first = $r['group_cost_movements']->where('balance_id', $pool->id)->first();
            $previous = DB::table('group_cost_movements')->where('balance_id', $pool->id)
                ->where('id', '<', $first->id)->orderByDesc('id')->first();
            DB::table('group_cost_pools')->where('id', $pool->id)->update([
                'on_hand_pig_count' => $first->pig_count_before,
                'total_carrying_value' => $first->value_before,
                'total_weight_kg' => $first->weight_before,
                'last_movement_id' => $previous?->id,
                'last_movement_at' => $previous?->occurred_at,
                'first_movement_at' => $previous ? $pool->first_movement_at : null,
                'closed_at' => $first->pig_count_before == 0 && bccomp((string) $first->value_before, '0', 4) === 0
                    && bccomp((string) $first->weight_before, '0', 4) === 0 ? now() : null,
                'updated_at' => now(),
            ]);
        }
        $gone('group_cost_movements');

        // Herd values: take out exactly the feed cost that went in.
        foreach ($r['biological_asset_stage_transfers'] as $move) {
            if ($move->to_stage_key !== null) {
                DB::table('biological_asset_stage_balances')->where('organization_id', $org)->where('group_id', $move->group_id)
                    ->where('stage_key', $move->to_stage_key)->update(['carrying_value' => DB::raw('carrying_value - '.(float) $move->amount), 'updated_at' => now()]);
            }
            if ($move->from_stage_key !== null) {
                DB::table('biological_asset_stage_balances')->where('organization_id', $org)->where('group_id', $move->group_id)
                    ->where('stage_key', $move->from_stage_key)->update(['carrying_value' => DB::raw('carrying_value + '.(float) $move->amount), 'updated_at' => now()]);
            }
        }
        $gone('biological_asset_stage_transfers');
        // A stage balance that held nothing but feed, and has no other history, goes too.
        $remaining = DB::table('biological_asset_stage_transfers')->where('organization_id', $org)->get(['group_id', 'to_stage_key', 'from_stage_key']);
        DB::table('biological_asset_stage_balances')->where('organization_id', $org)->where('pig_count', 0)
            ->whereBetween('carrying_value', [-0.0001, 0.0001])->get()
            ->reject(fn ($b) => $remaining->contains(fn ($m) => (int) $m->group_id === (int) $b->group_id && in_array($b->stage_key, [$m->to_stage_key, $m->from_stage_key], true)))
            ->each(fn ($b) => DB::table('biological_asset_stage_balances')->where('id', $b->id)->delete());

        // The stock ledger: a balance points at its last movement, and movements at their balance.
        DB::table('inventory_balances')->whereIn('id', $ids('inventory_balances') ?: [0])->update(['last_movement_id' => null]);
        $gone('inventory_movements');
        $gone('inventory_balances');

        // Feed activity, then setup.
        $gone('stock_count_lines');
        $gone('stock_counts');
        $gone('feed_issues');
        $gone('feed_production_plans');
        $gone('feed_batch_items');
        $gone('feed_batches');
        $gone('feed_stage_overrides');
        $gone('feeding_standards');
        $gone('feed_recipe_versions');
        $gone('feed_type_recipes');
        $gone('feed_types');
        $gone('feed_reminders');

        // Purchasing.
        $gone('gr_ir_entries');
        $gone('payment_run_lines');
        DB::table('payment_allocations')->whereIn('id', $ids('payment_allocations') ?: [0])->update(['reversal_of_allocation_id' => null]);
        $gone('payment_allocations');
        $gone('payments');
        DB::table('raw_material_stock_logs')->whereIn('id', $ids('raw_material_stock_logs') ?: [0])->update(['vendor_invoice_line_id' => null, 'purchase_request_item_id' => null]);
        $gone('vendor_invoice_lines');
        $gone('vendor_invoices');
        $gone('raw_material_stock_logs');
        $gone('purchase_request_items');
        $gone('purchase_requests');
        $gone('raw_materials');

        // Accounting.
        $gone('journal_lines');
        DB::table('journals')->whereIn('id', $ids('journals') ?: [0])->update(['reverses_journal_id' => null]);
        $gone('journals');
        $gone('journal_postings');
        DB::table('journal_batches')->whereIn('id', $ids('journal_batches') ?: [0])->update(['reverses_batch_id' => null, 'reversed_by_batch_id' => null]);
        $gone('journal_batches');
        $gone('domain_events');
    }
}
