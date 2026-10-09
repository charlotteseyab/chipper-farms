<x-app-layout>
    <main class="min-h-screen bg-slate-50 px-4 py-6 pb-28 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <section class="overflow-hidden rounded-3xl border border-emerald-200 bg-white shadow-sm">
                <div class="bg-gradient-to-r from-emerald-950 via-emerald-900 to-emerald-700 px-6 py-7 text-white sm:px-8">
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-emerald-200">Physical tag register</p>
                    <h1 class="mt-2 text-2xl font-black sm:text-3xl">Ear tag reconciliation</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-emerald-100">Review physical tags and their assignment history. Archived tags remain pending until the physical tag is confirmed as recovered, lost, damaged or retired.</p>
                </div>
                <div class="grid grid-cols-2 divide-x divide-y divide-slate-100 sm:grid-cols-4 lg:grid-cols-7 lg:divide-y-0">
                    @foreach(\App\Models\EarTag::STATUSES as $status)
                        <div class="p-4"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ str($status)->replace('_', ' ')->headline() }}</p><p class="mt-1 text-2xl font-black text-slate-950">{{ number_format($stats[$status] ?? 0) }}</p></div>
                    @endforeach
                </div>
            </section>

            @if(session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <form method="GET" class="grid gap-3 border-b border-slate-200 bg-slate-50/70 p-5 sm:grid-cols-2 lg:grid-cols-[1fr_180px_180px_auto]">
                        <input name="q" value="{{ request('q') }}" placeholder="Tag, colour or animal code" class="rounded-xl border-slate-300 text-sm">
                        <select name="status" class="rounded-xl border-slate-300 text-sm"><option value="">All statuses</option>@foreach(\App\Models\EarTag::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>@endforeach</select>
                        <select name="color_id" class="rounded-xl border-slate-300 text-sm"><option value="">All colours</option>@foreach($colors as $color)<option value="{{ $color->id }}" @selected((string) request('color_id') === (string) $color->id)>{{ $color->name }}</option>@endforeach</select>
                        <div class="flex gap-2"><button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Filter</button><a href="{{ route('settings.ear-tags.reconciliation') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700">Reset</a></div>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="min-w-[940px] w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50"><tr>@foreach(['Physical tag','Status','Current animal','Uses','Review outcome'] as $heading)<th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">{{ $heading }}</th>@endforeach</tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($tags as $tag)
                                    <tr class="align-top">
                                        <td class="px-5 py-4"><div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full border border-slate-300" style="background: {{ $tag->color->hex_color ?: '#CBD5E1' }}"></span><strong class="text-slate-950">{{ $tag->color->name }} {{ $tag->tag_number }}</strong></div><p class="mt-1 text-xs text-slate-500"><a class="underline" href="{{ route('ear-tags.history', $tag) }}">Current and previous holders</a></p></td>
                                        <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">{{ str($tag->status)->replace('_', ' ')->headline() }}</span></td>
                                        <td class="px-5 py-4 text-sm">@if($tag->currentAssignment?->pig)<strong>{{ $tag->currentAssignment->pig->code }}</strong><div class="text-xs text-slate-500">Tag {{ $tag->currentAssignment->pig->ear_tag_number }}</div>@else<span class="text-slate-400">No current holder</span>@endif</td>
                                        <td class="px-5 py-4 text-sm font-bold text-slate-700">{{ max(0, $tag->assignments_count - 1) }} reuse(s)<p class="text-xs">Last assigned {{ $tag->assignments->max('assigned_at') ?? 'Never' }}<br>Last released {{ $tag->assignments->max('released_at') ?? 'Never' }}</p></td>
                                        <td class="px-5 py-4">
                                            <form method="POST" action="{{ route('settings.ear-tags.reconciliation.update', $tag) }}" class="grid grid-cols-2 gap-2">@csrf @method('PATCH')
                                                    <select name="ear_tag_color_id" class="rounded-lg border-slate-300 text-xs">@foreach($colors->where('is_active', true) as $color)<option value="{{ $color->id }}" @selected($tag->ear_tag_color_id === $color->id)>{{ $color->name }}</option>@endforeach</select>
                                                    @if($tag->currentAssignment)
                                                        <input type="hidden" name="status" value="in_use">
                                                        <div class="flex items-center rounded-lg bg-emerald-50 px-3 text-xs font-bold text-emerald-800">In use</div>
                                                    @else
                                                        <select name="status" class="rounded-lg border-slate-300 text-xs">@foreach(['pending_recovery','available','lost','damaged','reserved','retired'] as $status)<option value="{{ $status }}" @selected($tag->status === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>@endforeach</select>
                                                    @endif
                                                    <input name="condition_notes" value="{{ $tag->condition_notes }}" placeholder="{{ $tag->currentAssignment ? 'Colour confirmation note' : 'Physical condition notes' }}" class="col-span-2 rounded-lg border-slate-300 text-xs">
                                                    <button class="col-span-2 rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">{{ $tag->currentAssignment ? 'Confirm colour' : 'Save review' }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty<tr><td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500">No tag-register records match these filters. Run the reconciliation backfill after migration.</td></tr>@endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($tags->hasPages())<div class="border-t border-slate-200 p-5">{{ $tags->links() }}</div>@endif
                </section>

                <aside class="h-fit rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-950">Add tag colour</h2>
                    <p class="mt-1 text-sm text-slate-500">Create the farm’s real physical colours, then move tags out of Unspecified during review.</p>
                    <form method="POST" action="{{ route('settings.ear-tags.colors.store') }}" class="mt-5 space-y-4">@csrf
                        <div><label class="mb-1 block text-xs font-bold text-slate-600">Colour name</label><input name="name" required placeholder="e.g. Green" class="w-full rounded-xl border-slate-300"></div>
                        <div><label class="mb-1 block text-xs font-bold text-slate-600">Short code</label><input name="short_code" placeholder="e.g. GRN" class="w-full rounded-xl border-slate-300"></div>
                        <div><label class="mb-1 block text-xs font-bold text-slate-600">Display colour</label><input type="color" name="hex_color" value="#15803d" class="h-11 w-full rounded-xl border border-slate-300 bg-white p-1"></div>
                        <button class="w-full rounded-xl bg-emerald-700 px-4 py-3 text-sm font-black text-white">Add colour</button>
                    </form>
                    <div class="mt-6 rounded-2xl bg-amber-50 p-4 text-xs leading-5 text-amber-900">Only physically recovered, usable tags should be marked Available. Colour and number together identify a tag within the farm. Green 1 and Yellow 1 are different tags.</div>
                </aside>
            </div>
        </div>
    </main>
</x-app-layout>
