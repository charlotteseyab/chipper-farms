{{-- One production plan: confirm it (with actual ingredient amounts) or cancel it. --}}
<details class="rounded-2xl border bg-white p-4 space-y-3" @if($open ?? false) open @endif><summary class="font-semibold cursor-pointer">Plan #{{ $plan->id }} · {{ $plan->feedType?->name }} · {{ \App\Support\Qty::kg($plan->planned_quantity_kg) }} kg · {{ ['planned' => 'Awaiting production', 'completed' => 'Completed', 'cancelled' => 'Cancelled'][$plan->status] ?? $plan->status }}</summary><p>Recipe {{ $plan->recipe_snapshot['version_id'] ?? 'legacy snapshot' }} · Planned {{ $plan->production_date->toDateString() }}</p>
            @if($plan->status==='planned' && auth()->user()->hasPermission('manage_feed_production'))
            <form method="POST" action="{{ route('feed-production.plans.complete',$plan) }}" class="space-y-3">@csrf
                <div class="grid sm:grid-cols-3 gap-3"><label>Production date<input name="batch_date" type="date" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required class="block rounded border-slate-300"></label><label>Finished feed produced (kg)<input name="finished_quantity_kg" type="number" min="0.0001" step="0.0001" value="{{ \App\Support\Qty::input($plan->planned_quantity_kg) }}" required class="block rounded border-slate-300"></label></div>
                @foreach($plan->recipe_snapshot['ingredients'] as $ingredient)<label class="block">{{ $ingredient['name'] }} — expected {{ \App\Support\Qty::kg($plan->planned_quantity_kg*$ingredient['quantity_per_100kg']/100) }} kg; actual used (kg)<input name="actual_quantities[{{ $ingredient['raw_material_id'] }}]" type="number" min="0.0001" step="0.0001" value="{{ $plan->planned_quantity_kg*$ingredient['quantity_per_100kg']/100 }}" required class="block rounded border-slate-300"></label>@endforeach
                <button class="rounded bg-emerald-700 px-4 py-2 text-white">Confirm production and post stock</button>
            </form>
            <details class="rounded-xl border border-red-100 p-3"><summary class="cursor-pointer text-sm font-semibold text-red-700">Not making this? Cancel the plan</summary>
                <form method="POST" action="{{ route('feed-production.plans.cancel', $plan) }}" class="mt-3 flex flex-wrap items-end gap-3">@csrf
                    <label class="flex-1 text-sm">Reason<input name="reason" required minlength="5" maxlength="2000" class="mt-1 block w-full rounded border-slate-300 text-sm"></label>
                    <button class="rounded bg-red-600 px-4 py-2 text-sm font-semibold text-white">Cancel plan</button>
                </form>
            </details>
            @elseif($plan->status === 'cancelled')<p class="text-sm text-slate-500">Cancelled {{ $plan->cancelled_at?->format('j M Y') }}: {{ $plan->cancellation_reason }}</p>
            @elseif($plan->feed_batch_id)<p>Completed as batch #{{ $plan->feed_batch_id }}. Inventory already posted.</p>@endif
        </details>
