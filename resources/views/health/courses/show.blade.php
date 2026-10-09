<x-app-layout>
    @php
        $doseStyle = ['scheduled' => 'bg-sky-100 text-sky-800', 'administered' => 'bg-emerald-100 text-emerald-800', 'missed' => 'bg-amber-100 text-amber-800', 'cancelled' => 'bg-slate-100 text-slate-500'];
    @endphp
    <div class="max-w-5xl mx-auto px-4 pt-6 pb-24 space-y-6">
        <div>
            @if($course->healthRecord)<a href="{{ route('health-cases.show', $course->healthRecord) }}" class="text-sm text-slate-500 hover:text-slate-700">← Case: {{ $course->healthRecord->condition }}</a>@endif
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900">{{ ucfirst($course->purpose) }} course #{{ $course->id }}</h1>
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $course->status === 'active' ? 'bg-sky-100 text-sky-800' : 'bg-slate-200 text-slate-700' }}">{{ ucfirst($course->status) }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-500">
                @if($course->prescriber_name)Prescribed by {{ $course->prescriber_name }} · @endif
                @if($course->responsibleUser)Responsible: {{ $course->responsibleUser->name }} · @endif
                {{ $course->animals->count() }} animal(s)@if($course->follow_up_on) · follow up {{ $course->follow_up_on->format('j M Y') }}@endif
            </p>
        </div>
        @if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</p>@endif
        @if(session('error'))<p role="alert" class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</p>@endif
        @if($course->amendedFrom)
            <p class="rounded-xl bg-slate-50 p-3 text-sm text-slate-700">Amends <a class="font-semibold underline" href="{{ route('treatment-courses.show', $course->amendedFrom) }}">course #{{ $course->amendedFrom->id }}</a>: {{ $course->amendment_reason }}</p>
        @endif
        @if($amendedBy)
            <p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Replaced by <a class="font-semibold underline" href="{{ route('treatment-courses.show', $amendedBy) }}">course #{{ $amendedBy->id }}</a>. These instructions are kept for the record.</p>
        @elseif($course->status !== 'active' && $course->status_reason)
            <p class="rounded-xl bg-slate-50 p-3 text-sm text-slate-700">{{ ucfirst($course->status) }}: {{ $course->status_reason }}</p>
        @endif

        <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="font-bold text-slate-900">Animals</h2>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach($course->animals as $animal)
                    <a href="{{ route('health.show', $animal->pig_id) }}" class="rounded-full bg-slate-100 px-3 py-1 text-sm hover:bg-slate-200">{{ $animal->pig?->code }} <span class="text-xs text-slate-500">{{ $animal->tag_label_snapshot }}</span></a>
                @endforeach
            </div>
        </section>

        @foreach($course->medicines as $line)
            @php $doses = $line->scheduledDoses->sortBy('due_at'); @endphp
            <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm space-y-3">
                <div>
                    <h2 class="font-bold text-slate-900">{{ $line->medicine?->display_name }}</h2>
                    <p class="text-sm text-slate-600">{{ rtrim(rtrim((string) $line->dose_amount, '0'), '.') }} {{ $line->dose_unit }} per animal{{ $line->route ? ', '.$line->route : '' }} ·
                        {{ $line->frequency === 'once' ? 'once' : (\App\Models\TreatmentCourseMedicine::FREQUENCIES[$line->frequency]).($line->interval ? ' ('.$line->interval.')' : '').', '.$line->number_of_doses.' doses' }}
                        · {{ $line->start_at->format('j M H:i') }} to {{ $line->end_at?->format('j M H:i') }}</p>
                    @if($line->instructions)<p class="text-sm text-slate-500">{{ $line->instructions }}</p>@endif
                    <p class="text-xs {{ $line->medicine?->meat_withdrawal_days === null ? 'text-amber-700' : 'text-slate-500' }}">{{ $line->medicine?->meat_withdrawal_days === null ? 'No withdrawal recorded for this medicine.' : 'Withdrawal: '.$line->medicine->meat_withdrawal_days.' days after the dose.' }}</p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                    @foreach($doses->groupBy('status') as $state => $group)
                        <span class="rounded-full px-2 py-0.5 font-semibold {{ $doseStyle[$state] ?? '' }}">{{ $group->count() }} {{ $state }}</span>
                    @endforeach
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-2">Due</th><th>Animal</th><th>Status</th><th>Note</th><th></th></tr></thead>
                        @foreach($doses->groupBy(fn ($d) => $d->due_at->format('Y-m-d H:i')) as $due => $slot)
                            @php $open = $slot->where('status', 'scheduled'); $overdue = $open->isNotEmpty() && $slot->first()->due_at->lt(now()); @endphp
                            <tbody class="border-t border-slate-200">
                                @if($open->isNotEmpty() && $course->status === 'active')
                                    @can('create', \App\Models\MedicationDosingEvent::class)
                                        <tr class="{{ $overdue ? 'bg-amber-50' : 'bg-slate-50' }}">
                                            <td colspan="5" class="px-2 py-1.5">
                                                <a href="{{ route('health.create-medication', ['scheduled' => $open->modelKeys()]) }}" class="text-sm font-semibold text-emerald-700 hover:underline">
                                                    Record dose for {{ $open->count() }} animal(s) due {{ $slot->first()->due_at->format('D j M H:i') }}</a>
                                                @if($overdue)<span class="ml-2 text-xs font-semibold text-amber-800">Overdue</span>@endif
                                            </td>
                                        </tr>
                                    @endcan
                                @endif
                                @foreach($slot as $dose)
                                    <tr class="border-t border-slate-100">
                                        <td class="py-1.5 font-mono">{{ $dose->due_at->format('D j M H:i') }}</td>
                                        <td>{{ $dose->pig?->code }}</td>
                                        <td><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $doseStyle[$dose->status] ?? '' }}">{{ ucfirst($dose->status) }}</span></td>
                                        <td class="text-xs text-slate-500">
                                            {{ $dose->status_reason }}
                                            @if($dose->administration?->medication_dosing_event_id)<a class="underline" href="{{ route('doses.show', $dose->administration->medication_dosing_event_id) }}">Dose #{{ $dose->administration->medication_dosing_event_id }}</a>@endif
                                        </td>
                                        <td class="text-right">
                                            @can('close', $dose)
                                                <details class="inline-block text-left">
                                                    <summary class="cursor-pointer text-xs font-semibold text-slate-600">Not given…</summary>
                                                    <form method="POST" action="{{ route('scheduled-doses.close', $dose) }}" class="mt-2 w-64 max-w-full text-left space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                                        @csrf
                                                        <select name="status" class="block w-full rounded-lg border-slate-300 text-sm">
                                                            <option value="missed">Missed</option>
                                                            <option value="cancelled">Cancelled</option>
                                                        </select>
                                                        <input name="reason" required minlength="3" placeholder="Why" class="block w-full rounded-lg border-slate-300 text-sm">
                                                        <button class="w-full rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white">Save (no stock taken)</button>
                                                    </form>
                                                </details>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @endforeach
                    </table>
                </div>
            </section>
        @endforeach

        @can('change', $course)
            <div class="flex flex-wrap items-start gap-3">
                <a href="{{ route('treatment-courses.edit', $course) }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Amend course</a>
                <details class="rounded-xl border border-red-100 bg-white p-3">
                    <summary class="cursor-pointer text-sm font-semibold text-red-700">Stop course</summary>
                    <form method="POST" action="{{ route('treatment-courses.stop', $course) }}" class="mt-2 flex flex-wrap gap-2">
                        @csrf
                        <input name="stop_reason" required minlength="5" placeholder="Why it is being stopped" class="rounded-lg border-slate-300 text-sm">
                        <button class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-semibold text-white">Stop and cancel remaining doses</button>
                    </form>
                </details>
            </div>
        @endcan
    </div>
</x-app-layout>
