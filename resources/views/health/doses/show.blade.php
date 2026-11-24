<x-app-layout>
    @php
        $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.') ?: '0';
        $badge = ['recorded' => 'bg-emerald-100 text-emerald-800', 'corrected' => 'bg-slate-200 text-slate-700', 'voided' => 'bg-red-100 text-red-700'];
        $first = $event->administrations->first();
    @endphp
    <div class="max-w-4xl mx-auto px-4 pt-6 pb-24 space-y-6">
        <div>
            <a href="{{ route('medications.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Medications</a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900">Dose #{{ $event->id }}: {{ $event->medicine?->display_name }}</h1>
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $badge[$event->status] ?? '' }}">{{ ucfirst($event->status) }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-500">Given {{ $event->administered_at->format('D j M Y, H:i') }}@if($event->givenBy) by {{ $event->givenBy->name }}@endif{{ $event->route ? ' · '.$event->route : '' }}@if($event->recordedBy && $event->recorded_by !== $event->administered_by) · entered by {{ $event->recordedBy->name }}@endif</p>
        </div>
        @if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</p>@endif
        @if(session('error'))<p role="alert" class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</p>@endif

        @if($event->status !== 'recorded')
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                <p class="font-semibold text-slate-900">{{ ucfirst($event->status) }}: {{ $event->correction_reason }}</p>
                <p class="mt-1">Its stock was returned to the batch and it no longer counts towards withdrawal.</p>
                @if($replacement)<a href="{{ route('doses.show', $replacement) }}" class="mt-2 inline-block font-semibold text-emerald-700 underline">View the corrected record (#{{ $replacement->id }})</a>@endif
            </div>
        @endif
        @if($event->correctsEvent)
            <p class="rounded-xl border border-sky-100 bg-sky-50 p-4 text-sm text-sky-900">Corrects <a class="font-semibold underline" href="{{ route('doses.show', $event->correctsEvent) }}">dose #{{ $event->correctsEvent->id }}</a>: {{ $event->correction_reason }}</p>
        @endif

        <section class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Per animal</p>
                <p class="mt-1 text-lg font-bold text-slate-900">{{ $qty($first?->dose_amount) }} {{ $first?->dose_unit }}</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Taken from stock</p>
                <p class="mt-1 text-lg font-bold text-slate-900">{{ $qty($event->total_stock_quantity) }} {{ $event->stock_unit }}</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Withdrawal</p>
                <p class="mt-1 text-lg font-bold {{ $first?->withdrawal_days_snapshot === null ? 'text-amber-700' : 'text-slate-900' }}">{{ $first?->withdrawal_days_snapshot === null ? 'Not recorded' : $first->withdrawal_days_snapshot.' days' }}</p>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="font-bold text-slate-900">Animals ({{ $animals->total() }})</h2>
            <div class="health-table-wrap">
              <div class="overflow-x-auto">
                <table class="health-table min-w-[560px]">
                    <thead><tr><th>Animal</th><th>Tag that day</th><th>Can be sold from</th><th>Course / case</th></tr></thead>
                    <tbody>
                        @foreach($animals as $dose)
                            <tr>
                                <td><a class="font-semibold text-emerald-700 hover:underline" href="{{ route('health.show', $dose->pig_id) }}">{{ $dose->pig?->code }}</a></td>
                                <td>{{ $dose->pig?->tagLabelAt($dose->administered_at) ?? '—' }}</td>
                                <td>{{ $dose->isVoided() ? '—' : ($dose->withdrawal_ends_on ? $dose->withdrawal_ends_on->copy()->addDay()->format('d M Y') : 'No withdrawal recorded') }}</td>
                                <td class="text-xs">
                                    @if($dose->treatmentCourse)<a class="underline" href="{{ route('treatment-courses.show', $dose->treatmentCourse) }}">Course #{{ $dose->treatment_course_id }}</a>@endif
                                    @if($dose->healthRecord)<a class="underline" href="{{ route('health-cases.show', $dose->healthRecord) }}">{{ $dose->healthRecord->condition }}</a>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
              </div>
              <div class="health-table-footer"><x-health.pagination :paginator="$animals" label="animals" /></div>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="font-bold text-slate-900">Stock taken</h2>
            <div class="health-table-wrap">
              <div class="overflow-x-auto">
            <table class="health-table min-w-[480px]">
                <thead><tr><th>Batch</th><th>Expiry</th><th class="num">Quantity</th><th class="num">Cost</th></tr></thead>
                <tbody>
                    @foreach($event->allocations as $allocation)
                        <tr>
                            <td>{{ $allocation->batch?->batch_number }}</td>
                            <td>{{ $allocation->expiry_snapshot->format('d M Y') }}</td>
                            <td class="text-right tabular-nums">{{ $qty($allocation->stock_quantity) }} {{ $event->stock_unit }}</td>
                            <td class="text-right tabular-nums">{{ number_format((float) bcmul((string) $allocation->stock_quantity, (string) $allocation->unit_cost_snapshot, 4), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
              </div>
            </div>
        </section>

        @if($event->notes)<p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-700 whitespace-pre-line">{{ $event->notes }}</p>@endif

        @can('correct', $event)
            <div class="flex flex-wrap items-start gap-3">
                <a href="{{ route('doses.edit', $event) }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Correct dose</a>
                <details class="rounded-xl border border-red-100 bg-white p-3">
                    <summary class="cursor-pointer text-sm font-semibold text-red-700">Void dose</summary>
                    <form method="POST" action="{{ route('doses.void', $event) }}" class="mt-2 space-y-2">
                        @csrf
                        <p class="text-xs text-slate-500">For a dose that was never given. The stock goes back to its batch and the withdrawal stops applying. The record stays, marked void.</p>
                        <input name="void_reason" required minlength="5" maxlength="2000" placeholder="Why is this record wrong?" class="block w-full rounded-lg border-slate-300 text-sm">
                        @error('void_reason')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        <button class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-semibold text-white">Void and return stock</button>
                    </form>
                </details>
            </div>
        @endcan
    </div>
</x-app-layout>
