<x-app-layout>
    @php
        $live = $visit->examinations->whereNull('voided_at');
        $chip = ['no_issue_found' => 'bg-emerald-100 text-emerald-800', 'abnormal_findings' => 'bg-red-100 text-red-800', 'pending_assessment' => 'bg-amber-100 text-amber-800'];
        $finalised = $visit->isFinalised();
    @endphp
    <div class="max-w-6xl mx-auto px-4 pt-6 pb-24 space-y-6">
        <div>
            <a href="{{ route('vet-visits.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Vet visits</a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900">{{ $visit->veterinarian_name }}, {{ $visit->visited_at->format('j M Y') }}</h1>
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $finalised ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $finalised ? 'Finalised' : 'Draft' }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-500">{{ $visit->visited_at->format('H:i') }}@if($visit->clinic) · {{ $visit->clinic }}@endif @if($visit->room) · {{ $visit->room->name }}@endif · notes entered by {{ $visit->enteredBy?->name ?? '—' }}</p>
        </div>
        <x-health.glance :cards="$glance" />
        @if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</p>@endif
        @if(session('error'))<p role="alert" class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</p>@endif
        @if($errors->any())<ul role="alert" class="list-disc list-inside rounded-xl bg-red-50 p-3 text-sm text-red-700">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif

        <div class="grid gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-100 bg-white p-4"><p class="text-xs text-slate-500">Animals examined</p><p class="text-2xl font-bold">{{ $live->count() }}</p></div>
            <div class="rounded-xl border border-slate-100 bg-white p-4"><p class="text-xs text-slate-500">No issue found</p><p class="text-2xl font-bold text-emerald-700">{{ $live->where('assessment', 'no_issue_found')->count() }}</p></div>
            <div class="rounded-xl border border-slate-100 bg-white p-4"><p class="text-xs text-slate-500">Abnormal findings</p><p class="text-2xl font-bold text-red-700">{{ $live->where('assessment', 'abnormal_findings')->count() }}</p></div>
            <div class="rounded-xl border border-slate-100 bg-white p-4"><p class="text-xs text-slate-500">Assessment pending</p><p class="text-2xl font-bold text-amber-700">{{ $live->where('assessment', 'pending_assessment')->count() }}</p></div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
            <div class="space-y-4 min-w-0">
                <h2 class="text-lg font-bold text-slate-900">Animals examined</h2>
                @forelse($visit->examinations as $exam)
                    <article id="exam-{{ $exam->id }}" class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm {{ $exam->voided_at ? 'opacity-60' : '' }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <a href="{{ route('health.show', $exam->pig_id) }}" class="font-bold text-slate-900 hover:underline">{{ $exam->pig?->code }}</a>
                                <span class="text-sm text-slate-500">{{ $exam->tag_label_at_visit }} · {{ $exam->examined_at->format('H:i') }}{{ $exam->repeat_number ? ' · repeat examination' : '' }}</span>
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $exam->voided_at ? 'bg-slate-200 text-slate-600' : $chip[$exam->assessment] }}">{{ $exam->voided_at ? 'Voided' : \App\Models\VetExamination::ASSESSMENTS[$exam->assessment] }}</span>
                        </div>
                        <dl class="mt-2 grid gap-2 text-sm sm:grid-cols-2">
                            @foreach(['findings' => 'Findings', 'assessment_text' => 'Assessment', 'vet_notes' => "Vet's notes", 'recommendations' => 'Recommendations', 'tests_requested' => 'Tests requested', 'test_results' => 'Test results'] as $field => $label)
                                @if($exam->$field)<div><dt class="text-slate-500">{{ $label }}</dt><dd class="text-slate-800">{{ $exam->$field }}</dd></div>@endif
                            @endforeach
                        </dl>
                        <p class="mt-2 text-xs text-slate-500">
                            {{ $exam->follow_up_on ? 'Follow up '.$exam->follow_up_on->format('j M Y') : ($exam->follow_up_not_needed ? 'No follow-up needed' : 'No follow-up set') }}
                            @if($exam->healthRecord) · <a class="font-semibold text-emerald-700 underline" href="{{ route('health-cases.show', $exam->healthRecord) }}">Case: {{ $exam->healthRecord->condition }}</a>@endif
                            @if($exam->voided_at) · voided: {{ $exam->void_reason }}@endif
                        </p>
                        @if(! $exam->voided_at)
                            @can('update', $visit)
                                <details class="mt-2 text-sm">
                                    <summary class="cursor-pointer text-red-700">Void this examination</summary>
                                    <form method="POST" action="{{ route('vet-visits.examinations.void', [$visit, $exam]) }}" class="mt-2 flex flex-wrap gap-2">
                                        @csrf
                                        <input name="void_reason" required minlength="5" placeholder="Why it is wrong" class="rounded-lg border-slate-300 text-sm">
                                        <button class="rounded-lg bg-red-600 px-3 py-1.5 font-semibold text-white">Void</button>
                                    </form>
                                </details>
                            @endcan
                        @endif
                        @include('health.partials.attachments', ['parent' => $exam, 'compact' => true])
                    </article>
                @empty
                    <p class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500">No animal examined yet. Add each one with the form.</p>
                @endforelse
            </div>

            <aside class="space-y-5 min-w-0">
                @can('update', $visit)
                    <section class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                        <h2 class="font-bold text-slate-900">Add an examination</h2>
                        <form method="POST" action="{{ route('vet-visits.examinations.store', $visit) }}" class="mt-3 space-y-3 text-sm" x-data="{ assessment: @js(old('assessment', 'no_issue_found')) }">
                            @csrf
                            <x-health.pig-picker name="pig_id" :multiple="false" label="Animal" />
                            <label class="block font-medium text-slate-700">Assessment
                                <select name="assessment" x-model="assessment" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                    @foreach(\App\Models\VetExamination::ASSESSMENTS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                            </label>
                            <input type="datetime-local" name="examined_at" value="{{ $visit->visited_at->format('Y-m-d\TH:i') }}" max="{{ now()->format('Y-m-d\TH:i') }}" class="block w-full rounded-lg border-slate-300 text-sm" aria-label="Examined at">
                            <textarea name="findings" rows="2" placeholder="Findings" class="block w-full rounded-lg border-slate-300 text-sm"></textarea>
                            <textarea name="assessment_text" rows="1" placeholder="Assessment or diagnosis" class="block w-full rounded-lg border-slate-300 text-sm"></textarea>
                            <textarea name="vet_notes" rows="2" placeholder="Vet's notes for this animal" class="block w-full rounded-lg border-slate-300 text-sm"></textarea>
                            <textarea name="recommendations" rows="1" placeholder="Recommendations" class="block w-full rounded-lg border-slate-300 text-sm"></textarea>
                            <input name="tests_requested" placeholder="Tests requested (optional)" class="block w-full rounded-lg border-slate-300 text-sm">
                            <div class="grid grid-cols-2 gap-2">
                                <label class="text-slate-700">Follow up on<input type="date" name="follow_up_on" min="{{ now()->toDateString() }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
                                <label class="mt-6 flex items-center gap-2 text-slate-700"><input type="checkbox" name="follow_up_not_needed" value="1" class="rounded border-slate-300"> Not needed</label>
                            </div>
                            <div x-show="assessment === 'abnormal_findings'" class="space-y-2 rounded-lg bg-red-50 p-3">
                                <label class="flex items-center gap-2"><input type="checkbox" name="open_case" value="1" class="rounded border-slate-300"> Open a health case for this</label>
                                <input name="case_condition" placeholder="Condition for the case" class="block w-full rounded-lg border-slate-300 text-sm">
                                <select name="health_record_id" class="block w-full rounded-lg border-slate-300 text-sm">
                                    <option value="">…or link an existing open case</option>
                                    @foreach($openCases as $case)<option value="{{ $case->id }}">{{ $case->pig?->code }}: {{ $case->condition }} ({{ $case->record_date->format('j M') }})</option>@endforeach
                                </select>
                            </div>
                            <label class="flex items-center gap-2 text-slate-700"><input type="checkbox" name="is_repeat" value="1" class="rounded border-slate-300"> Repeat examination of an animal already checked on this visit</label>
                            @if($finalised)<input name="amendment_reason" required minlength="5" placeholder="Visit is finalised: why are you adding this?" class="block w-full rounded-lg border-slate-300 text-sm">@endif
                            <button class="rounded-lg bg-emerald-700 px-4 py-2 font-semibold text-white">Save examination</button>
                        </form>
                    </section>
                @endcan

                @if($notYetExamined->isNotEmpty())
                    <section class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                        <h2 class="font-bold text-slate-900">Planned, not yet examined</h2>
                        <p class="text-xs text-slate-500">Not counted as examined.</p>
                        <div class="mt-2 flex flex-wrap gap-2">@foreach($notYetExamined as $planned)<span class="rounded-full bg-slate-100 px-3 py-1 text-sm">{{ $planned->pig?->code }}</span>@endforeach</div>
                    </section>
                @endif

                <section class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm space-y-2 text-sm">
                    <h2 class="font-bold text-slate-900">Visit notes</h2>
                    <p><span class="text-slate-500">Reason:</span> {{ $visit->reason ?: '—' }}</p>
                    <p><span class="text-slate-500">General notes:</span> {{ $visit->general_notes ?: '—' }}</p>
                    <p><span class="text-slate-500">Recommendations:</span> {{ $visit->recommendations ?: '—' }}</p>
                    @can('update', $visit)
                        <details>
                            <summary class="cursor-pointer font-semibold text-emerald-700">Edit visit details</summary>
                            <form method="POST" action="{{ route('vet-visits.update', $visit) }}" class="mt-3 space-y-3">
                                @csrf @method('PUT')
                                @include('health.visits._fields', ['visit' => $visit])
                                @if($finalised)<input name="amendment_reason" required minlength="5" placeholder="Visit is finalised: why is it changing?" class="block w-full rounded-lg border-slate-300 text-sm">@endif
                                <button class="rounded-lg bg-slate-800 px-3 py-1.5 font-semibold text-white">Save</button>
                            </form>
                        </details>
                    @endcan
                    @include('health.partials.attachments', ['parent' => $visit])
                    @can('finalise', $visit)
                        <form method="POST" action="{{ route('vet-visits.finalise', $visit) }}" class="pt-2">
                            @csrf
                            <button class="w-full rounded-lg bg-emerald-700 px-3 py-2 font-semibold text-white">Finalise visit</button>
                            <p class="mt-1 text-xs text-slate-500">After finalising, changes need a manager and a reason.</p>
                        </form>
                    @endcan
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
