<x-app-layout>
    <main class="min-h-screen bg-slate-50/70 py-4">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Header --}}
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Roles &amp; access</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Choose which parts of the system each role on this farm can use. Changes apply only to this farm.
                    Admins always have full access, so you can't lock yourself out.
                </p>
            </div>

            @if (auth()->user()->isSuperuser())
                <form method="GET" action="{{ route('settings.roles-access') }}"
                      class="flex flex-col gap-3 rounded-3xl border border-slate-200 bg-white px-5 py-4 shadow-sm sm:flex-row sm:items-end sm:px-6">
                    <label class="flex-1">
                        <span class="text-sm font-semibold text-slate-900">Farm</span>
                        <select name="farm" onchange="this.form.submit()"
                                class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">Choose a farm…</option>
                            @foreach ($farms as $option)
                                <option value="{{ $option->id }}" @selected($organization?->id === $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <noscript><button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Show</button></noscript>
                </form>
            @endif

            @if (session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>
            @endif

            @if (! $organization)
                <p class="rounded-3xl border border-dashed border-slate-300 bg-white px-5 py-8 text-center text-sm text-slate-500">
                    Choose a farm to see and change what its roles can use.
                </p>
            @else
                @php
                    $farmQuery = auth()->user()->isSuperuser() ? ['farm' => $organization->id] : [];
                    $badge = [
                        'none' => ['No access', 'bg-slate-100 text-slate-500 ring-slate-200'],
                        'view' => ['View', 'bg-sky-50 text-sky-700 ring-sky-200'],
                        'full' => ['Full', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
                        'custom' => ['Custom', 'bg-amber-50 text-amber-700 ring-amber-200'],
                    ];
                @endphp

                {{-- Overview: every role at a glance --}}
                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                        <h2 class="text-base font-semibold text-slate-900">At a glance</h2>
                        <p class="mt-0.5 text-sm text-slate-600">Pick a role to change it.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-3 sm:px-6">Module</th>
                                    <th class="px-3 py-3 text-center">Admin</th>
                                    @foreach ($roles as $role)
                                        <th class="px-3 py-3 text-center">
                                            <a href="{{ route('settings.roles-access', [...$farmQuery, 'role' => $role['id']]) }}"
                                               class="hover:text-emerald-700 {{ $selected['id'] === $role['id'] ? 'text-emerald-700 underline underline-offset-4' : '' }}">
                                                {{ $role['label'] }}
                                            </a>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($modules as $key => $module)
                                    <tr>
                                        <td class="whitespace-nowrap px-5 py-3 font-medium text-slate-800 sm:px-6">
                                            <i class="{{ $module['icon'] }} mr-2 w-4 text-slate-400"></i>{{ $module['label'] }}
                                        </td>
                                        <td class="px-3 py-3 text-center">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $badge['full'][1] }}">Full</span>
                                        </td>
                                        @foreach ($roles as $role)
                                            @php [$text, $classes] = $badge[$role['levels'][$key]]; @endphp
                                            <td class="px-3 py-3 text-center">
                                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $classes }}">{{ $text }}</span>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                {{-- One role --}}
                @if ($selected)
                    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"
                             x-data="roleAccess(@js(array_map(fn ($m) => ['permissions' => $m['permissions'], 'view' => \App\Support\Access\RoleAccessCatalog::viewPermissions($m)], $modules)), @js($selected['granted']))">
                        <div class="border-b border-slate-100 px-5 pt-4 sm:px-6">
                            <div class="flex flex-wrap gap-2">
                                @foreach ($roles as $role)
                                    <a href="{{ route('settings.roles-access', [...$farmQuery, 'role' => $role['id']]) }}"
                                       class="rounded-full px-3 py-1.5 text-sm font-medium transition {{ $selected['id'] === $role['id'] ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                                        {{ $role['label'] }}
                                        @if ($role['customised'])<span class="ml-1 inline-block h-1.5 w-1.5 rounded-full {{ $selected['id'] === $role['id'] ? 'bg-white' : 'bg-amber-500' }}" title="Changed for this farm"></span>@endif
                                    </a>
                                @endforeach
                            </div>
                            <div class="flex flex-col gap-1 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h2 class="text-base font-semibold text-slate-900">{{ $selected['label'] }}</h2>
                                    <p class="text-sm text-slate-600">
                                        {{ $selected['users'] }} {{ $selected['users'] === 1 ? 'person' : 'people' }} on this farm.
                                        @if ($selected['customised'])
                                            Changed for this farm{{ $selected['updated_by'] ? ' by '.$selected['updated_by'] : '' }}{{ $selected['updated_at'] ? ' on '.\Illuminate\Support\Carbon::parse($selected['updated_at'])->format('j M Y') : '' }}.
                                        @else
                                            Using the default access for this role.
                                        @endif
                                    </p>
                                </div>
                                @if ($selected['customised'])
                                    <form method="POST" action="{{ route('settings.roles-access.reset', $selected['id']) }}"
                                          onsubmit="return confirm('Put {{ $selected['label'] }} back to its default access on this farm?')">
                                        @csrf
                                        @method('DELETE')
                                        @if ($farmQuery)<input type="hidden" name="farm" value="{{ $organization->id }}">@endif
                                        <button type="submit" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                            Reset to default
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <form method="POST" action="{{ route('settings.roles-access.update', $selected['id']) }}">
                            @csrf
                            @method('PUT')
                            @if ($farmQuery)<input type="hidden" name="farm" value="{{ $organization->id }}">@endif

                            <div class="divide-y divide-slate-100">
                                @foreach ($modules as $key => $module)
                                    <div class="px-5 py-4 sm:px-6" x-data="{ open: false }">
                                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                            <div class="flex items-start gap-3">
                                                <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                                                    <i class="{{ $module['icon'] }}"></i>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-slate-900">{{ $module['label'] }}</p>
                                                    <p class="text-sm text-slate-500">{{ $module['description'] }}</p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <div class="inline-flex rounded-xl bg-slate-100 p-1 text-sm font-medium">
                                                    <button type="button" @click="setLevel('{{ $key }}', 'none')"
                                                            :class="level('{{ $key }}') === 'none' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                                                            class="rounded-lg px-3 py-1.5">No access</button>
                                                    <button type="button" @click="setLevel('{{ $key }}', 'view')"
                                                            :class="level('{{ $key }}') === 'view' ? 'bg-white text-sky-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                                                            class="rounded-lg px-3 py-1.5">View</button>
                                                    <button type="button" @click="setLevel('{{ $key }}', 'full')"
                                                            :class="level('{{ $key }}') === 'full' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                                                            class="rounded-lg px-3 py-1.5">Full</button>
                                                </div>
                                                <button type="button" @click="open = ! open"
                                                        class="whitespace-nowrap text-sm font-medium"
                                                        :class="level('{{ $key }}') === 'custom' ? 'text-amber-700' : 'text-slate-500 hover:text-slate-800'">
                                                    <span x-text="level('{{ $key }}') === 'custom' ? 'Custom' : 'Details'"></span>
                                                    <i class="fa-solid fa-chevron-down ml-1 text-xs transition" :class="open && 'rotate-180'"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div x-show="open" x-cloak class="mt-3 grid gap-2 rounded-2xl bg-slate-50 p-3 sm:grid-cols-2">
                                            @foreach ($module['permissions'] as $permission)
                                                <label class="flex items-start gap-2 rounded-xl px-2 py-1.5 hover:bg-white">
                                                    <input type="checkbox" name="permissions[]" value="{{ $permission }}" x-model="granted"
                                                           class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                                    <span class="text-sm">
                                                        <span class="font-medium text-slate-800">{{ \App\Support\Access\RoleAccessCatalog::permissionLabel($permission) }}</span>
                                                        @if (str_starts_with($permission, 'view_'))<span class="ml-1 rounded bg-sky-100 px-1 text-[10px] font-semibold uppercase text-sky-700">view</span>@endif
                                                        @if ($descriptions[$permission] ?? null)
                                                            <span class="block text-xs text-slate-500">{{ $descriptions[$permission] }}</span>
                                                        @endif
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/80 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <p class="text-xs text-slate-500">Saving affects everyone with the {{ $selected['label'] }} role on this farm. Records are never deleted.</p>
                                <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                                    Save {{ $selected['label'] }}
                                </button>
                            </div>
                        </form>
                    </section>
                @endif
            @endif
        </div>
    </main>

    <script>
        function roleAccess(modules, granted) {
            return {
                granted: granted,
                level(key) {
                    const all = modules[key].permissions;
                    const held = all.filter(p => this.granted.includes(p));
                    if (held.length === 0) return 'none';
                    if (held.length === all.length) return 'full';
                    const view = modules[key].view;
                    if (held.length === view.length && view.every(p => held.includes(p))) return 'view';
                    return 'custom';
                },
                setLevel(key, level) {
                    const all = modules[key].permissions;
                    const keep = this.granted.filter(p => ! all.includes(p));
                    const add = level === 'full' ? all : (level === 'view' ? modules[key].view : []);
                    this.granted = [...keep, ...add];
                },
            };
        }
    </script>
</x-app-layout>
