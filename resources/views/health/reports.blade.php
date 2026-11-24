<x-app-layout>
    @php
        $tabs = \App\Services\Health\HealthReportService::TABS;
        $q = fn (array $extra = []) => array_merge(request()->except('page'), $extra);
        $input = 'mt-1 block w-full rounded-lg border-slate-300 text-sm';
        $hidden = ['medicine_id', 'pig_id'];
        // Where each row on a paginated tab links to.
        $link = fn ($item) => match ($tab) {
            'cases' => route('health-cases.show', $item->id),
            'doses' => $item->medication_dosing_event_id ? route('doses.show', $item->medication_dosing_event_id) : route('medications.show', $item->id),
            'visits' => route('vet-visits.show', $item->id),
            default => null,
        };
    @endphp
    <div class="max-w-7xl mx-auto px-4 pt-6 pb-24 space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="{{ route('health.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Health</a>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">Health Reports</h1>
                @unless($tab === 'stock')<p class="text-sm text-slate-500">{{ $from->format('j M Y') }} to {{ $to->format('j M Y') }}</p>@endunless
            </div>
        </div>

        <nav class="flex gap-1 overflow-x-auto border-b border-slate-200" aria-label="Reports">
            @foreach($tabs as $key => $label)
                <a href="{{ route('health.reports', ['tab' => $key]) }}" class="whitespace-nowrap border-b-2 px-3 py-2 text-sm font-semibold {{ $tab === $key ? 'border-emerald-700 text-emerald-800' : 'border-transparent text-slate-500 hover:text-slate-700' }}">{{ $label }}</a>
            @endforeach
        </nav>

        @if($shortened)<p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Reports cover at most a year. The start date was moved to {{ $from->format('j M Y') }}.</p>@endif

        @unless($tab === 'stock')
            <form method="GET" class="grid grid-cols-2 gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm sm:grid-cols-4 lg:grid-cols-6">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <label class="text-sm font-medium text-slate-700">From<input type="date" name="from" value="{{ $from->toDateString() }}" class="{{ $input }}"></label>
                <label class="text-sm font-medium text-slate-700">To<input type="date" name="to" value="{{ $to->toDateString() }}" class="{{ $input }}"></label>
                @if($tab === 'cases')
                    <label class="text-sm font-medium text-slate-700">Status
                        <select name="status" class="{{ $input }}"><option value="">Any</option>@foreach(\App\Services\Health\HealthReportService::CASE_STATUSES as $v => $l)<option value="{{ $v }}" @selected(($filters['status'] ?? '') === $v)>{{ $l }}</option>@endforeach</select>
                    </label>
                    <label class="text-sm font-medium text-slate-700">Condition<input name="condition" value="{{ $filters['condition'] ?? '' }}" class="{{ $input }}"></label>
                    <label class="text-sm font-medium text-slate-700">Animal code<input name="pig" value="{{ $filters['pig'] ?? '' }}" class="{{ $input }}"></label>
                    <label class="text-sm font-medium text-slate-700">Group<input name="group" value="{{ $filters['group'] ?? '' }}" placeholder="e.g. Pen 2" class="{{ $input }}"></label>
                @elseif($tab === 'doses')
                    <label class="text-sm font-medium text-slate-700">Medicine<input name="medicine" value="{{ $filters['medicine'] ?? '' }}" class="{{ $input }}"></label>
                    <label class="text-sm font-medium text-slate-700">Animal code<input name="pig" value="{{ $filters['pig'] ?? '' }}" class="{{ $input }}"></label>
                    <label class="text-sm font-medium text-slate-700">Status
                        <select name="status" class="{{ $input }}"><option value="">All</option>@foreach(['given' => 'Given (counts)', 'corrected' => 'Corrected', 'voided' => 'Voided', 'missed' => 'Missed', 'cancelled' => 'Cancelled'] as $v => $l)<option value="{{ $v }}" @selected(($filters['status'] ?? '') === $v)>{{ $l }}</option>@endforeach</select>
                    </label>
                    <label class="text-sm font-medium text-slate-700">Purpose<input name="purpose" value="{{ $filters['purpose'] ?? '' }}" class="{{ $input }}"></label>
                @elseif($tab === 'usage')
                    <label class="text-sm font-medium text-slate-700">Group by
                        <select name="by" class="{{ $input }}">@foreach(['medicine' => 'Medicine', 'batch' => 'Batch', 'animal' => 'Animal', 'group' => 'Group (case or course)'] as $v => $l)<option value="{{ $v }}" @selected(($filters['by'] ?? 'medicine') === $v)>{{ $l }}</option>@endforeach</select>
                    </label>
                    <label class="text-sm font-medium text-slate-700">Medicine<input name="medicine" value="{{ $filters['medicine'] ?? '' }}" class="{{ $input }}"></label>
                @elseif($tab === 'visits')
                    <label class="text-sm font-medium text-slate-700">Vet<input name="vet" value="{{ $filters['vet'] ?? '' }}" class="{{ $input }}"></label>
                    <label class="text-sm font-medium text-slate-700">Assessment
                        <select name="assessment" class="{{ $input }}"><option value="">Any</option>@foreach(\App\Models\VetExamination::ASSESSMENTS as $v => $l)<option value="{{ $v }}" @selected(($filters['assessment'] ?? '') === $v)>{{ $l }}</option>@endforeach</select>
                    </label>
                @endif
                <div class="col-span-2 flex items-end gap-2 sm:col-span-4 lg:col-span-6">
                    <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Apply</button>
                    <a href="{{ route('health.reports', ['tab' => $tab]) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600">Reset</a>
                    @if($canExport)<a href="{{ route('health.reports.export', ['tab' => $tab] + $q()) }}" class="ml-auto rounded-lg border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-800">Download CSV</a>@endif
                </div>
            </form>
        @endunless

        @if(isset($rows))
            @php $labels = ['cases' => 'cases', 'doses' => 'doses', 'visits' => 'visits']; @endphp
            <div class="health-table-wrap">
              <div class="overflow-x-auto">
                <table class="health-table min-w-[900px]">
                    <thead><tr>@foreach(array_keys(array_diff_key($rows->first() ?? [], array_flip($hidden))) as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                    <tbody>
                        @forelse($rows as $i => $row)
                            @php $url = $link($page->items()[$i]); @endphp
                            <tr>
                                @foreach(array_values(array_diff_key($row, array_flip($hidden))) as $c => $value)
                                    <td class="{{ $c === 0 ? 'whitespace-nowrap' : '' }}">@if($c === 0 && $url)<a href="{{ $url }}" class="font-semibold text-emerald-800 hover:underline">{{ $value }}</a>@else{{ $value }}@endif</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td class="health-table-empty">Nothing in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
              </div>
              <div class="health-table-footer"><x-health.pagination :paginator="$page" :label="$labels[$tab] ?? 'rows'" /></div>
            </div>
        @endif

        @if($tab === 'doses')
            <section class="space-y-2">
                <h2 class="font-bold text-slate-900">Scheduled doses not given</h2>
                @include('health.partials.report-table', ['rows' => $notGiven, 'empty' => 'No missed or cancelled doses in this period.', 'label' => 'doses not given'])
            </section>
        @endif

        @if($tab === 'usage')
            <div class="flex flex-wrap gap-3">
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Cost of medicine used</p><p class="text-2xl font-bold tabular-nums">{{ number_format((float) $usage['total_cost'], 2) }}</p></div>
                <div class="rounded-2xl border p-4 shadow-sm {{ $usage['ledger_matches'] ? 'border-emerald-100 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-800' }}">
                    <p class="text-xs uppercase">Stock ledger check</p>
                    <p class="font-semibold">{{ $usage['ledger_matches'] ? 'Matches the ledger' : 'Does not match the ledger. Report this.' }}</p>
                    <p class="text-xs">{{ $usage['allocations'] }} batch withdrawal(s) by doses still on record</p>
                </div>
            </div>
            @include('health.partials.report-table', ['rows' => $usageRows, 'empty' => 'No medicine used in this period.', 'label' => 'rows'])
            <p class="text-xs text-slate-500">Cost is the batch's cost when the dose was given. Medicine cost is not posted to the accounts (decision D10). A group is what the dose was for: the case's group name (or its condition for one animal), or the course for preventive doses.</p>
        @endif

        @if($tab === 'stock')
            <section class="space-y-2">
                <div class="flex items-center justify-between"><h2 class="font-bold text-slate-900">Batches in stock</h2>@if($canExport)<a href="{{ route('health.reports.export', ['tab' => 'batches']) }}" class="text-sm font-semibold text-emerald-800">Download CSV</a>@endif</div>
                @include('health.partials.report-table', ['rows' => $batches, 'empty' => 'No medicine in stock.', 'linkKey' => 'medicine_id', 'route' => 'medicines.show', 'label' => 'batches'])
            </section>
            <section class="space-y-2">
                <h2 class="font-bold text-slate-900">At or below reorder level</h2>
                @include('health.partials.report-table', ['rows' => $lowStock, 'empty' => 'Nothing is low.', 'linkKey' => 'medicine_id', 'route' => 'medicines.show', 'label' => 'medicines'])
            </section>
            <section id="withdrawal" class="space-y-2">
                <div class="flex items-center justify-between"><h2 class="font-bold text-slate-900">Animals under withdrawal today ({{ $withdrawal->total() }})</h2>@if($canExport)<a href="{{ route('health.reports.export', ['tab' => 'withdrawal']) }}" class="text-sm font-semibold text-emerald-800">Download CSV</a>@endif</div>
                @include('health.partials.report-table', ['rows' => $withdrawal, 'empty' => 'No animal on the farm is under withdrawal.', 'linkKey' => 'pig_id', 'route' => 'health.show', 'label' => 'animals'])
            </section>
        @endif

        <p class="text-xs text-slate-500">Mortality and morbidity rates are not shown until their definitions are agreed.</p>
    </div>
</x-app-layout>
