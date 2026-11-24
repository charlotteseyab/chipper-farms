<x-app-layout>
    <div class="max-w-5xl mx-auto px-4 pt-6 pb-24 space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="{{ route('health.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Health</a>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">Herd health programme</h1>
                <p class="text-sm text-slate-500">Routine vaccinations, deworming and other must-do doses. Set each one up once; every animal that qualifies is scheduled automatically, {{ \App\Services\Health\HealthProgrammeService::daysAhead() }} days ahead, and shows on the worklist and in health alerts.</p>
            </div>
            @can('create', \App\Models\TreatmentCourse::class)
                <a href="{{ route('health-programmes.create') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Add a programme dose</a>
            @endcan
        </div>

        @if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</p>@endif
        @if($errors->any())<ul role="alert" class="list-disc list-inside rounded-xl bg-red-50 p-4 text-sm text-red-700">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif

        @forelse($programmes as $programme)
            @php $waiting = $upcoming[$programme->id] ?? null; @endphp
            <article class="rounded-2xl border bg-white p-5 shadow-sm {{ $programme->is_active ? 'border-slate-100' : 'border-dashed border-slate-300 opacity-80' }}" x-data="{ pausing: false }">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ \App\Models\HealthProgramme::PURPOSES[$programme->purpose] ?? $programme->purpose }}</span>
                            @unless($programme->is_active)<span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Paused</span>@endunless
                        </div>
                        <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $programme->name }}</h2>
                        <p class="text-sm text-slate-600">
                            {{ $programme->ruleText() }} · {{ \App\Models\HealthProgramme::APPLIES_TO[$programme->applies_to] ?? $programme->applies_to }} · {{ substr((string) $programme->due_time, 0, 5) }}
                        </p>
                        <p class="text-sm text-slate-500">
                            {{ $programme->medicine?->display_name ?? 'Medicine removed' }}, {{ rtrim(rtrim((string) $programme->dose_amount, '0'), '.') }} {{ $programme->dose_unit }} per animal{{ $programme->route ? ', '.$programme->route : '' }}
                            @if($programme->responsibleUser) · {{ $programme->responsibleUser->name }} responsible @endif
                        </p>
                        @if(! $programme->is_active && $programme->paused_reason)<p class="mt-1 text-sm text-amber-800">Paused: {{ $programme->paused_reason }}</p>@endif
                    </div>
                    <div class="text-right text-sm">
                        @if($waiting)
                            <p class="font-semibold text-slate-900">{{ $waiting->doses }} {{ \Illuminate\Support\Str::plural('dose', $waiting->doses) }} scheduled</p>
                            <p class="text-slate-500">next {{ \Carbon\CarbonImmutable::parse($waiting->next_due)->format('D j M') }}</p>
                        @else
                            <p class="text-slate-500">Nothing scheduled yet</p>
                        @endif
                    </div>
                </div>
                @can('create', \App\Models\TreatmentCourse::class)
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <a href="{{ route('health-programmes.edit', $programme) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700">Edit</a>
                        @if($programme->is_active)
                            <button type="button" @click="pausing = ! pausing" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700">Pause</button>
                        @else
                            <form method="POST" action="{{ route('health-programmes.resume', $programme) }}">@csrf
                                <button class="rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-sm font-semibold text-emerald-700">Resume</button>
                            </form>
                        @endif
                    </div>
                    <form x-show="pausing" x-cloak method="POST" action="{{ route('health-programmes.pause', $programme) }}" class="mt-3 flex flex-wrap items-end gap-2">
                        @csrf
                        <label class="min-w-[16rem] flex-1 text-sm font-medium text-slate-700">Why is it being paused?
                            <input name="reason" required minlength="5" maxlength="2000" placeholder="e.g. Vet changed the vaccine; new programme to follow" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        </label>
                        <button class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white">Pause and cancel doses not yet given</button>
                    </form>
                @endcan
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500">
                <p class="font-semibold text-slate-700">No programme doses yet.</p>
                <p class="mt-1">Add the vaccinations and deworming your vet has set for the herd, for example "PCV2 vaccine at 21 days old", "Deworm the breeding herd every 180 days" or "E. coli vaccine 14 days before farrowing".</p>
            </div>
        @endforelse

        <p class="text-xs text-slate-500">To skip a dose for one animal (for example, it is sick), mark it cancelled on the worklist with the reason; it will not be scheduled again. Piglets still on the sow are counted on the litter, not as individual animals, so they are scheduled from the day they are weaned onto stock.</p>
    </div>
</x-app-layout>
