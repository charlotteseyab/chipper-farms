<?php

namespace App\Services\Feed;

use App\Models\FeedIssue;
use App\Models\FeedProductionPlan;
use App\Models\Finance\InventoryMovement;
use App\Models\RawMaterial;
use App\Models\Setting;
use App\Models\User;
use App\Services\Notifications\InAppNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Feed reminders, in the notification bell:
 *   - finished feed running low (under 7 days of cover) and "mix now" (under 3)
 *   - a stage with animals and a ration but no feeding recorded by 10:00
 *   - ingredients at or below their reorder level
 *   - not enough ingredients for the mixing the next 7 days need
 *   - a production plan waiting more than a day
 *   - a large planned-vs-actual difference, once a variance threshold is set
 *
 * Timings come from Feed Setup settings, falling back to config/feed.php.
 * Each reminder carries keys written to feed_reminders when sent, so it goes
 * out once; stock reminders are keyed on the last restock, so they come back
 * after the next delivery or batch runs low. Works on the organisation bound.
 */
class FeedReminderService
{
    public function __construct(
        protected FeedPlanningService $planning,
        protected InAppNotificationService $notifications,
    ) {
    }

    /** A setting from Feed Setup, or the default in config/feed.php when blank. */
    public static function setting(string $key): mixed
    {
        $value = Setting::get('feed.alerts.'.$key);

        return $value === null || $value === '' ? config('feed.reminders.'.$key) : $value;
    }

    /** @return array<string, int> reminders sent, by type */
    public function send(int $organizationId, CarbonImmutable $now): array
    {
        $sent = [];
        foreach ($this->due($organizationId, $now) as $reminder) {
            $delivered = DB::transaction(function () use ($organizationId, $reminder, $now) {
                $claimed = DB::table('feed_reminders')->insertOrIgnore(array_map(fn ($key) => [
                    'organization_id' => $organizationId, 'reminder_key' => $key, 'type' => $reminder['type'], 'sent_at' => $now,
                ], $reminder['keys']));
                if ($claimed === 0) {
                    return false;
                }
                $users = $this->notifications->notifyRelevantStaff($organizationId, $reminder['type'], $reminder['title'], $reminder['message'],
                    $reminder['link'], $reminder['severity'], ['reminder_keys' => $reminder['keys']], $this->recipientIds($organizationId, $reminder['permissions']));
                DB::table('feed_reminders')->where('organization_id', $organizationId)->whereIn('reminder_key', $reminder['keys'])
                    ->update(['recipients' => $users->count()]);

                return true;
            });
            if ($delivered) {
                $sent[$reminder['type']] = ($sent[$reminder['type']] ?? 0) + 1;
            }
        }

        return $sent;
    }

    /** Reminders whose keys have not been sent yet. */
    public function due(int $organizationId, CarbonImmutable $now): Collection
    {
        $all = collect()
            ->merge($this->finishedFeedLow($organizationId, $now))
            ->merge($this->feedingNotRecorded($organizationId, $now))
            ->merge($this->rawMaterialsLow())
            ->merge($this->ingredientsShort($organizationId, $now))
            ->merge($this->plansWaiting($now))
            ->merge($this->largeVariance($organizationId, $now));

        $sent = DB::table('feed_reminders')->where('organization_id', $organizationId)
            ->whereIn('reminder_key', $all->pluck('keys')->flatten()->all())->pluck('reminder_key')->flip();

        return $all->map(function (array $reminder) use ($sent) {
            $reminder['keys'] = array_values(array_filter($reminder['keys'], fn ($key) => ! isset($sent[$key])));

            return $reminder;
        })->filter(fn ($reminder) => $reminder['keys'] !== [])->values();
    }

    /** Days of cover from the next week's plan: under 3 is "mix now", under 7 "running low". */
    private function finishedFeedLow(int $orgId, CarbonImmutable $now): Collection
    {
        $low = (float) self::setting('cover_days');
        // A "running low" set at or below the "mix now" default makes everything under it urgent.
        $urgent = min((float) self::setting('cover_urgent_days'), $low);
        $week = $this->planning->calculate($orgId, $now->toDateString(), $now->addDays(6)->toDateString());

        return collect($week['feeds'])->filter(fn ($feed) => $feed['days_cover'] !== null && $feed['days_cover'] < $low)
            ->map(function (array $feed) use ($urgent) {
                $critical = $feed['days_cover'] < $urgent;
                $restock = $this->lastRestock(InventoryMovement::ITEM_KIND_FINISHED_FEED, (int) $feed['feed_type_id']);
                $days = rtrim(rtrim(number_format($feed['days_cover'], 1), '0'), '.');

                return [
                    'type' => $critical ? 'feed.stock_critical' : 'feed.stock_low',
                    'keys' => [($critical ? 'feed_critical:' : 'feed_low:').$feed['feed_type_id'].':'.$restock],
                    'title' => $critical ? "Mix {$feed['name']} now: about {$days} days left" : "{$feed['name']} is running low: about {$days} days left",
                    'message' => number_format((float) $feed['available_kg']).' kg in stock at the current rate of feeding.'
                        .($critical ? ' Mix a batch now, or feeding will run out.' : ' Plan the next mix and check the ingredients are in.'),
                    'link' => route('mill.plan'), 'severity' => $critical ? 'critical' : 'warning',
                    'permissions' => ['manage_feed_production'],
                ];
            })->values();
    }

    /** One reminder a day listing stages with animals and a ration but nothing recorded by the due time. */
    private function feedingNotRecorded(int $orgId, CarbonImmutable $now): Collection
    {
        $dueBy = CarbonImmutable::parse($now->toDateString().' '.self::setting('feeding_due_by'), $now->getTimezone());
        if ($now->lt($dueBy)) {
            return collect();
        }
        $today = $this->planning->calculate($orgId, $now->toDateString(), $now->toDateString(), applyWastage: false);
        $fed = FeedIssue::where('organization_id', $orgId)->notReversed()->whereNotNull('feeding_stage')
            ->whereDate('issued_at', $now->toDateString())->pluck('feeding_stage')->unique()->flip();
        $missing = collect($today['rows'])->filter(fn ($row) => (float) $row['daily_kg'] > 0 && ! isset($fed[$row['stage']]))
            ->pluck('stage')->unique()->values();
        if ($missing->isEmpty()) {
            return collect();
        }
        $labels = $missing->map(fn ($stage) => FeedStageResolver::STAGES[$stage] ?? $stage);

        return collect([[
            'type' => 'feed.not_recorded', 'keys' => $missing->map(fn ($stage) => "not_recorded:{$stage}:{$now->toDateString()}")->all(),
            'title' => 'Feeding not recorded yet: '.$labels->implode(', '),
            'message' => 'No feeding has been recorded today for '.$labels->implode(', ').' by '.$dueBy->format('H:i').'. Record what was fed, or check they were fed.',
            'link' => route('feed.issues.create'), 'severity' => 'warning',
            'permissions' => ['manage_feed_issues'],
        ]]);
    }

    /**
     * Ingredients at or below their reorder level (or empty), listed in one
     * reminder. Keyed per ingredient and restock, so a newly low ingredient
     * reminds again, and one that was restocked reminds after it runs low again.
     */
    private function rawMaterialsLow(): Collection
    {
        $low = RawMaterial::where('is_active', true)->where('reorder_level', '>', 0)->whereColumn('quantity_in_stock', '<=', 'reorder_level')
            ->orderBy('quantity_in_stock')->get();
        if ($low->isEmpty()) {
            return collect();
        }
        $out = $low->filter(fn ($m) => (float) $m->quantity_in_stock <= 0);
        $kg = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');

        return collect([[
            'type' => 'feed.raw_material_low',
            'keys' => $low->map(fn (RawMaterial $m) => "raw_low:{$m->id}:".$this->lastRestock(InventoryMovement::ITEM_KIND_RAW_MATERIAL, $m->id))->all(),
            'title' => $low->count() === 1
                ? ($out->isNotEmpty() ? "{$low->first()->name} has run out" : "{$low->first()->name} is low")
                : $low->count().' ingredients low'.($out->isNotEmpty() ? ', '.$out->count().' out' : ''),
            'message' => $low->take(8)->map(fn (RawMaterial $m) => $m->name.' '.$kg($m->quantity_in_stock).' kg (reorder at '.$kg($m->reorder_level).')')->implode('; ')
                .($low->count() > 8 ? ' and '.($low->count() - 8).' more' : '').'. Request a purchase from Store → Raw materials.',
            'link' => route('raw-materials.index', ['status' => $out->isNotEmpty() ? 'out_of_stock' : 'low_stock']),
            'severity' => $out->isNotEmpty() ? 'critical' : 'warning',
            'permissions' => ['manage_raw_materials', 'manage_feed_production'],
        ]]);
    }

    /** Not enough of an ingredient for the mixing the next week needs. Once a day while short, listing them together. */
    private function ingredientsShort(int $orgId, CarbonImmutable $now): Collection
    {
        $days = (int) self::setting('ingredient_days');
        $plan = $this->planning->calculate($orgId, $now->toDateString(), $now->addDays(max(1, $days) - 1)->toDateString());
        $short = collect($plan['materials'])->filter(fn ($m) => $m['shortfall_kg'] > 0)->values();
        if ($short->isEmpty()) {
            return collect();
        }

        return collect([[
            'type' => 'feed.ingredients_short', 'keys' => $short->map(fn ($m) => "ingredients_short:{$m['raw_material_id']}:{$now->toDateString()}")->all(),
            'title' => 'Not enough ingredients for the next '.$days.' days of mixing',
            'message' => $short->take(6)->map(fn ($m) => $m['name'].' short '.rtrim(rtrim(number_format((float) $m['shortfall_kg'], 1), '0'), '.').' kg')->implode('; ')
                .($short->count() > 6 ? ' and '.($short->count() - 6).' more' : '').'. Open the plan to request a purchase.',
            'link' => route('mill.plan'), 'severity' => 'critical',
            'permissions' => ['manage_feed_production', 'manage_raw_materials'],
        ]]);
    }

    /** A production plan nobody has confirmed or cancelled after a day. */
    private function plansWaiting(CarbonImmutable $now): Collection
    {
        $hours = (int) self::setting('plan_waiting_hours');

        return FeedProductionPlan::with('feedType')->where('status', 'planned')->where('created_at', '<=', $now->subHours($hours))->get()
            ->map(fn (FeedProductionPlan $plan) => [
                'type' => 'feed.plan_waiting', 'keys' => ["plan_waiting:{$plan->id}"],
                'title' => "Production plan #{$plan->id} is still waiting",
                'message' => rtrim(rtrim(number_format((float) $plan->planned_quantity_kg, 2), '0'), '.')." kg of {$plan->feedType?->name}, planned "
                    .$plan->created_at->format('j M').'. Confirm it once it is mixed, or cancel it with a reason.',
                'link' => route('feed-production.index'), 'severity' => 'info',
                'permissions' => ['manage_feed_production'],
            ])->values();
    }

    /** Yesterday's and today's feedings far from the ration, only once a variance threshold is set. */
    private function largeVariance(int $orgId, CarbonImmutable $now): Collection
    {
        $threshold = Setting::get('feed.alerts.variance_percent');
        if (! is_numeric($threshold)) {
            return collect();
        }
        $rows = collect($this->planning->consumption($orgId, $now->subDay()->toDateString(), $now->toDateString(), []))
            ->filter(fn ($row) => $row['variance_percent'] !== null && abs($row['variance_percent']) >= (float) $threshold);

        return $rows->groupBy('date')->map(fn (Collection $day, string $date) => [
            'type' => 'feed.variance', 'keys' => $day->map(fn ($row) => 'variance:'.$date.':'.implode('-', $row['issue_ids'] ?? []))->values()->all(),
            'title' => 'Feeding far from the ration on '.CarbonImmutable::parse($date)->format('j M'),
            'message' => $day->map(fn ($row) => $row['group'].': '.rtrim(rtrim(number_format($row['actual_kg'], 1), '0'), '.').' kg given against '
                .rtrim(rtrim(number_format((float) $row['planned_kg'], 1), '0'), '.').' kg')->implode('; ').'. Check for a typing slip and reverse it if so.',
            'link' => route('feed.planned-vs-actual', ['period' => 'custom', 'from' => $date, 'to' => $date]), 'severity' => 'warning',
            'permissions' => ['manage_feed_issues'],
        ])->values();
    }

    /** The latest movement that added stock, so stock reminders reset after a restock. */
    private function lastRestock(string $kind, int $itemId): int
    {
        return (int) InventoryMovement::where('item_kind', $kind)->where('item_id', $itemId)
            ->whereIn('event_type', InventoryMovement::POSITIVE_EVENTS)->max('id');
    }

    /** Farm managers, plus staff holding the permission the reminder is about. */
    private function recipientIds(int $organizationId, array $permissions): array
    {
        return User::where('organization_id', $organizationId)->where('active_status', true)->with('roleRecord.permissions')->get()
            ->filter(fn (User $u) => $u->isAdmin() || $u->isManager() || $u->hasRoleName('manager')
                || collect($permissions)->contains(fn ($p) => $u->hasPermission($p)))
            ->modelKeys();
    }
}
