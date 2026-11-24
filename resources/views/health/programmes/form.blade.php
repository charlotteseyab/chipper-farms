<x-app-layout>
    @php
        $editing = $programme !== null;
        $value = fn (string $field, $default = null) => old($field, $editing ? $programme->{$field} : $default);
        $catalogue = $medicines->mapWithKeys(fn ($m) => [$m->id => ['unit' => $m->dose_unit, 'routes' => $m->allowed_routes ?? []]]);
    @endphp
    <div class="max-w-3xl mx-auto px-4 pt-6 pb-24">
        <a href="{{ route('health-programmes.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Herd health programme</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $editing ? 'Edit '.$programme->name : 'Add a programme dose' }}</h1>
        <p class="text-sm text-slate-500">
            {{ $editing ? 'Changes apply to doses scheduled from now on. Doses already scheduled keep their date and dose.' : 'A dose every qualifying animal must get. Once saved, doses are scheduled automatically; nothing is taken from stock until a dose is recorded.' }}
        </p>

        @if($errors->any())<ul role="alert" class="mt-4 list-disc list-inside rounded-xl bg-red-50 p-4 text-sm text-red-700">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
        @if($medicines->isEmpty())
            <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">The medicine catalogue is empty. <a class="font-semibold underline" href="{{ route('medicines.create') }}">Add a medicine</a> first.</p>
        @endif

        <form method="POST" action="{{ $editing ? route('health-programmes.update', $programme) : route('health-programmes.store') }}" class="mt-5 space-y-5"
              x-data="{ trigger: @js($value('trigger', 'age')), medicine: @js((string) $value('medicine_id', '')), catalogue: @js($catalogue),
                        get breeding() { return ['before_mating', 'before_farrowing', 'after_farrowing'].includes(this.trigger) },
                        get daysLabel() { return { age: 'Age (days old)', interval: 'Repeat every (days)', before_mating: 'Days before the planned mating', before_farrowing: 'Days before expected farrowing', after_farrowing: 'Days after farrowing' }[this.trigger] } }">
            @csrf
            @if($editing) @method('PUT') @endif

            <div class="space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
                    <label class="text-sm font-medium text-slate-700">Name
                        <input name="name" value="{{ $value('name') }}" required maxlength="255" placeholder="e.g. PCV2 vaccine, growers" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Type
                        <select name="purpose" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            @foreach(\App\Models\HealthProgramme::PURPOSES as $key => $label)<option value="{{ $key }}" @selected($value('purpose', 'vaccination') === $key)>{{ $label }}</option>@endforeach
                        </select>
                    </label>
                </div>
            </div>

            <div class="space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <h2 class="font-bold text-slate-900">When it is due</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm font-medium text-slate-700">Due
                        <select name="trigger" x-model="trigger" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            @foreach(\App\Models\HealthProgramme::TRIGGERS as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <label class="text-sm font-medium text-slate-700"><span x-text="daysLabel"></span>
                        <input type="number" name="days" min="0" max="3650" value="{{ $value('days') }}" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700" x-show="! breeding">Which animals
                        <select name="applies_to" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            @foreach(\App\Models\HealthProgramme::APPLIES_TO as $key => $label)<option value="{{ $key }}" @selected($value('applies_to', 'all') === $key)>{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <p class="self-end text-sm text-slate-500" x-show="breeding" x-cloak>Applies to breeding sows and gilts, following each one's own breeding record.</p>
                    <label class="text-sm font-medium text-slate-700">Time of day
                        <input type="time" name="due_time" value="{{ substr((string) $value('due_time', '08:00'), 0, 5) }}" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700"><span x-text="trigger === 'interval' ? 'First date' : 'Start scheduling from'"></span>
                        <input type="date" name="starts_on" value="{{ $editing ? $programme->starts_on->toDateString() : old('starts_on', now()->toDateString()) }}" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                </div>
                <p class="text-xs text-slate-500" x-show="trigger === 'age'">Animals already older than this when the programme starts, or added to the system after this age, are not scheduled. Record their dose by hand if they still need it.</p>
                <p class="text-xs text-slate-500" x-show="trigger === 'interval'" x-cloak>Every animal in the group on each date is scheduled together, for example the whole breeding herd every 180 days.</p>
                <p class="text-xs text-slate-500" x-show="trigger === 'before_farrowing'" x-cloak>Scheduled once the sow is confirmed pregnant, counted back from her expected farrowing date.</p>
            </div>

            <div class="space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <h2 class="font-bold text-slate-900">The dose</h2>
                <div class="grid gap-4 sm:grid-cols-[2fr_1fr_1fr]">
                    <label class="text-sm font-medium text-slate-700">Medicine
                        <select name="medicine_id" x-model="medicine" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            <option value="">Choose</option>
                            @foreach($medicines as $medicine)<option value="{{ $medicine->id }}">{{ $medicine->display_name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="text-sm font-medium text-slate-700">Dose per animal
                        <div class="relative mt-1">
                            <input type="number" step="0.0001" min="0.0001" name="dose_amount" value="{{ $editing ? rtrim(rtrim((string) $programme->dose_amount, '0'), '.') : old('dose_amount') }}" required class="block w-full rounded-lg border-slate-300 pr-14 text-sm">
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-500" x-text="catalogue[medicine]?.unit ?? ''"></span>
                        </div>
                    </label>
                    <label class="text-sm font-medium text-slate-700">Route
                        <input name="route" value="{{ $value('route') }}" list="programme-routes" maxlength="50" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        <datalist id="programme-routes"><template x-for="r in (catalogue[medicine]?.routes ?? [])"><option :value="r"></option></template></datalist>
                    </label>
                </div>
                <label class="block text-sm font-medium text-slate-700">Instructions
                    <textarea name="instructions" rows="2" maxlength="2000" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" placeholder="e.g. Neck, behind the ear. Shake well.">{{ $value('instructions') }}</textarea>
                </label>
                <label class="block text-sm font-medium text-slate-700 sm:w-1/2">Responsible person
                    <select name="responsible_user_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        <option value="">—</option>
                        @foreach($staff as $person)<option value="{{ $person->id }}" @selected((string) $value('responsible_user_id') === (string) $person->id)>{{ $person->name }}</option>@endforeach
                    </select>
                </label>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('health-programmes.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel</a>
                <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">{{ $editing ? 'Save changes' : 'Save and schedule' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
