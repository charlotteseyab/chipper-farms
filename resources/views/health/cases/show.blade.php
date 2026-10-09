<x-app-layout>
    @php
        $statusStyle = ['open' => 'bg-amber-100 text-amber-800', 'under_treatment' => 'bg-sky-100 text-sky-800', 'resolved' => 'bg-emerald-100 text-emerald-800', 'closed' => 'bg-slate-200 text-slate-700'];
        $status = $case->case_status ?? 'open';
        $when = $case->observed_at ?? $case->record_date;
    @endphp
    <div class="max-w-6xl mx-auto px-4 pt-6 pb-24 space-y-6">
        <div>
            <a href="{{ route('health.show', $case->pig_id) }}" class="text-sm text-slate-500 hover:text-slate-700">← {{ $case->pig?->code }} health</a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900">{{ $case->condition }}</h1>
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $case->isVoided() ? 'bg-slate-200 text-slate-600' : $statusStyle[$status] }}">{{ $case->isVoided() ? 'Voided' : (\App\Models\HealthRecord::CASE_STATUSES[$status] ?? $status) }}</span>
                @if($case->severity)<span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ ucfirst($case->severity) }}</span>@endif
            </div>
            <p class="mt-1 text-sm text-slate-500">
                {{ $case->scope === 'group' ? 'Group case' : 'Case' }} #{{ $case->id }} · seen {{ \Carbon\Carbon::parse($when)->format('j M Y, H:i') }}
                · recorded by {{ $case->recordedBy?->name ?? '—' }}@if($case->responsibleUser) · responsible: {{ $case->responsibleUser->name }}@endif
            </p>
        </div>

        @if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</p>@endif
        @if(session('error'))<p role="alert" class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</p>@endif
        @if($errors->any())<ul role="alert" class="list-disc list-inside rounded-xl bg-red-50 p-3 text-sm text-red-700">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
        @if($case->isVoided())
            <p class="rounded-xl bg-slate-100 p-3 text-sm text-slate-700">Voided {{ $case->voided_at->format('j M Y') }}: {{ $case->void_reason }}</p>
        @endif

        <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
            <div class="space-y-6 min-w-0">
                <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm space-y-3">
                    <h2 class="font-bold text-slate-900">Findings</h2>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-slate-500">Symptoms</dt><dd class="text-slate-900">{{ $case->symptoms ?: '—' }}</dd></div>
                        <div><dt class="text-slate-500">Diagnosis</dt><dd class="text-slate-900">{{ $case->diagnosis ?: 'Not known' }}@if($case->diagnosis_status) <span class="text-xs text-slate-500">({{ \App\Models\HealthRecord::DIAGNOSIS_STATUSES[$case->diagnosis_status] }})</span>@endif</dd></div>
                        @if($case->vetExamination)
                            <div><dt class="text-slate-500">Found at</dt><dd><a class="text-emerald-700 underline" href="{{ route('vet-visits.show', $case->vetExamination->vet_visit_id) }}">Vet visit, {{ $case->vetExamination->visit?->visited_at?->format('j M Y') }}</a></dd></div>
                        @endif
                        @if($case->outcome)
                            <div><dt class="text-slate-500">Outcome</dt><dd class="text-slate-900">{{ \App\Models\HealthRecord::OUTCOMES[$case->outcome] ?? $case->outcome }}@if($case->closure_reason) — {{ $case->closure_reason }}@endif</dd></div>
                        @endif
                    </dl>
                    <div>
                        <p class="text-sm text-slate-500">{{ $case->animals->count() > 1 ? $case->animals->count().' animals' : 'Animal' }}@if($case->group_label_snapshot) · {{ $case->group_label_snapshot }}@endif</p>
                        <div class="mt-1 flex flex-wrap gap-2">
                            @foreach($case->animals as $animal)
                                <a href="{{ route('health.show', $animal->pig_id) }}" class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-800 hover:bg-slate-200">{{ $animal->pig?->code }} <span class="text-xs text-slate-500">{{ $animal->tag_label_snapshot }}</span></a>
                            @endforeach
                        </div>
                    </div>
                    @can('update', $case)<a href="{{ route('health.records.edit', $case) }}" class="inline-block text-sm font-semibold text-emerald-700 underline">Edit findings</a>@endcan
                    @include('health.partials.attachments', ['parent' => $case])
                </section>

                <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-bold text-slate-900">Treatment courses</h2>
                        @if(! $case->isVoided() && $status !== 'closed')
                            @can('create', \App\Models\TreatmentCourse::class)
                                <a href="{{ route('treatment-courses.create', ['case' => $case->id]) }}" class="rounded-lg bg-emerald-700 px-3 py-1.5 text-sm font-semibold text-white">Prescribe a course</a>
                            @endcan
                            @can('create', \App\Models\MedicationDosingEvent::class)
                                <a href="{{ route('health.create-medication', ['case' => $case->id]) }}" class="rounded-lg border border-emerald-700 px-3 py-1.5 text-sm font-semibold text-emerald-800">Record a single dose</a>
                            @endcan
                        @endif
                    </div>
                    @forelse($case->treatmentCourses as $course)
                        <a href="{{ route('treatment-courses.show', $course) }}" class="block rounded-xl border border-slate-100 p-3 hover:bg-slate-50">
                            <p class="font-semibold text-slate-900">{{ ucfirst($course->purpose) }} · {{ $course->medicines->map(fn ($m) => $m->medicine?->name)->implode(', ') }}</p>
                            <p class="text-xs text-slate-500">{{ ucfirst($course->status) }} · {{ $course->animals->count() }} animal(s)@if($course->prescriber_name) · prescribed by {{ $course->prescriber_name }}@endif</p>
                        </a>
                    @empty
                        <p class="text-sm text-slate-500">No course prescribed yet.</p>
                    @endforelse
                </section>

                <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm space-y-3">
                    <h2 class="font-bold text-slate-900">Doses given since the case opened</h2>
                    @forelse($doses as $dose)
                        <a href="{{ $dose->medication_dosing_event_id ? route('doses.show', $dose->medication_dosing_event_id) : route('medications.show', $dose) }}" class="flex flex-wrap justify-between gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50 {{ $dose->isVoided() ? 'text-slate-400 line-through' : 'text-slate-800' }}">
                            <span>{{ $dose->administration_date->format('j M') }} · {{ $dose->pig?->code }} · {{ $dose->medicine_name_snapshot ?? $dose->medication_name }} {{ $dose->dosage }}</span>
                            <span class="text-xs text-slate-500">{{ $dose->withdrawal_period_days === null ? 'no withdrawal recorded' : $dose->withdrawal_period_days.' days withdrawal' }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-slate-500">No doses recorded for these animals since the case opened.</p>
                    @endforelse
                </section>

                @if($notGiven->isNotEmpty())
                    <section class="rounded-2xl border border-amber-100 bg-white p-5 shadow-sm space-y-2">
                        <h2 class="font-bold text-slate-900">Doses not given ({{ $notGiven->count() }})</h2>
                        @foreach($notGiven as $dose)
                            <div class="flex flex-wrap justify-between gap-2 border-l-2 {{ $dose->status === 'missed' ? 'border-amber-400' : 'border-slate-300' }} pl-3 text-sm">
                                <span><span class="font-semibold">{{ $dose->pig?->code }}</span> · {{ $dose->courseMedicine?->medicine?->name }} due {{ $dose->due_at->format('j M H:i') }}</span>
                                <span class="text-slate-600">{{ ucfirst($dose->status) }}{{ $dose->status_reason ? ': '.$dose->status_reason : '' }}</span>
                            </div>
                        @endforeach
                    </section>
                @endif
            </div>

            <aside class="space-y-6 min-w-0">
                <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm space-y-3">
                    <h2 class="font-bold text-slate-900">Follow-ups</h2>
                    @forelse($case->followUps as $followUp)
                        <div class="border-l-2 {{ $followUp->is_closing ? 'border-emerald-500' : 'border-slate-200' }} pl-3 text-sm">
                            <p class="font-semibold text-slate-900">{{ $followUp->observed_at->format('j M Y') }} · {{ $followUp->is_closing ? 'Closing note' : (\App\Models\HealthFollowUp::RESPONSES[$followUp->response] ?? 'Noted') }}</p>
                            @if($followUp->findings)<p class="text-slate-700">{{ $followUp->findings }}</p>@endif
                            @if($followUp->adverse_reaction)<p class="text-red-700">Adverse reaction: {{ $followUp->adverse_reaction_details }}</p>@endif
                            <p class="text-xs text-slate-400">{{ $followUp->recorder?->name }}</p>
                            @include('health.partials.attachments', ['parent' => $followUp, 'compact' => true])
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">None yet.</p>
                    @endforelse

                    @can('followUp', $case)
                        <details class="rounded-xl border border-slate-100 p-3" @if($errors->has('response') || $errors->has('observed_at')) open @endif>
                            <summary class="cursor-pointer text-sm font-semibold text-emerald-700">Add a follow-up</summary>
                            <form method="POST" action="{{ route('health-cases.follow-ups.store', $case) }}" class="mt-3 space-y-2 text-sm">
                                @csrf
                                <input type="datetime-local" name="observed_at" required max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ now()->format('Y-m-d\TH:i') }}" class="block w-full rounded-lg border-slate-300 text-sm" aria-label="When">
                                <select name="response" class="block w-full rounded-lg border-slate-300 text-sm" aria-label="Response">
                                    <option value="">How is it going?</option>
                                    @foreach(\App\Models\HealthFollowUp::RESPONSES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                                <textarea name="findings" rows="2" placeholder="What you saw" class="block w-full rounded-lg border-slate-300 text-sm"></textarea>
                                <textarea name="complications" rows="1" placeholder="Complications (optional)" class="block w-full rounded-lg border-slate-300 text-sm"></textarea>
                                <label class="flex items-center gap-2"><input type="checkbox" name="adverse_reaction" value="1" class="rounded border-slate-300"> Adverse reaction to treatment</label>
                                <input name="adverse_reaction_details" placeholder="Describe the reaction" class="block w-full rounded-lg border-slate-300 text-sm">
                                <button class="rounded-lg bg-emerald-700 px-3 py-1.5 font-semibold text-white">Save follow-up</button>
                            </form>
                        </details>
                        <details class="rounded-xl border border-slate-100 p-3" @if($errors->has('outcome') || $errors->has('closed_at')) open @endif>
                            <summary class="cursor-pointer text-sm font-semibold text-slate-800">Resolve or close the case</summary>
                            <form method="POST" action="{{ route('health-cases.close', $case) }}" class="mt-3 space-y-2 text-sm">
                                @csrf
                                <select name="outcome" required class="block w-full rounded-lg border-slate-300 text-sm" aria-label="Outcome">
                                    <option value="">Outcome</option>
                                    @foreach(\App\Models\HealthRecord::OUTCOMES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                                <select name="final_status" required class="block w-full rounded-lg border-slate-300 text-sm" aria-label="Status">
                                    <option value="resolved">Resolved — recovered, keep watching</option>
                                    <option value="closed">Closed — no further action</option>
                                </select>
                                <input type="datetime-local" name="closed_at" required max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ now()->format('Y-m-d\TH:i') }}" class="block w-full rounded-lg border-slate-300 text-sm" aria-label="Date">
                                <textarea name="closure_notes" rows="2" placeholder="Notes" class="block w-full rounded-lg border-slate-300 text-sm"></textarea>
                                <p class="text-xs text-slate-500">Finishing a course does not close a case; this does.</p>
                                <button class="rounded-lg bg-slate-800 px-3 py-1.5 font-semibold text-white">Save</button>
                            </form>
                        </details>
                    @endcan
                    @can('reopen', $case)
                        <details class="rounded-xl border border-slate-100 p-3">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-800">Reopen</summary>
                            <form method="POST" action="{{ route('health-cases.reopen', $case) }}" class="mt-3 space-y-2 text-sm">
                                @csrf
                                <input name="reopen_reason" required minlength="5" placeholder="Why it is being reopened" class="block w-full rounded-lg border-slate-300 text-sm">
                                <button class="rounded-lg bg-slate-800 px-3 py-1.5 font-semibold text-white">Reopen case</button>
                            </form>
                        </details>
                    @endcan
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
