<x-app-layout>
    @php
        $actionTone = fn (string $action) => match (true) {
            str_contains($action, 'delete'), str_contains($action, 'declin'), str_contains($action, 'fail'), str_contains($action, 'cancel') => 'bg-red-50 text-red-700 border-red-200',
            str_contains($action, 'approv'), str_contains($action, 'complet'), str_contains($action, 'created'), $action === 'login' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            str_contains($action, 'payment'), str_contains($action, 'paid'), str_contains($action, 'receiv') => 'bg-blue-50 text-blue-700 border-blue-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    @endphp

    <main class="space-y-5 pb-8">
        <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 px-6 py-7 text-white shadow-xl sm:px-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div><p class="text-xs font-bold uppercase tracking-[.2em] text-emerald-300">System accountability</p><h1 class="mt-2 text-3xl font-black text-white">Activity log</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">A chronological record of security events and meaningful changes across stock, breeding, health, feed, procurement, storefront, finance and settings.</p></div>
                <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-sm text-slate-200"><span class="font-bold text-white">{{ auth()->user()->isSuperuser() ? 'Platform view' : auth()->user()->organization?->name }}</span><span class="mt-1 block text-xs text-slate-300">Audit entries are append-only.</span></div>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['Total activity', $summary['total'], 'Matches the current filters', 'border-slate-200', 'text-slate-600'],
                ['Recorded today', $summary['today'], 'Successful actions and security events', 'border-emerald-200', 'text-emerald-600'],
                ['Active users', $summary['users'], 'Distinct users in this result', 'border-blue-200', 'text-blue-600'],
                ['Needs attention', $summary['attention'], 'Failed, declined, deleted or cancelled', 'border-red-200', 'text-red-600'],
            ] as [$label,$value,$help,$border,$text])
                <article class="rounded-2xl border {{ $border }} bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wider {{ $text }}">{{ $label }}</p><p class="mt-2 text-3xl font-black text-slate-950">{{ number_format($value) }}</p><p class="mt-2 text-xs leading-5 text-slate-500">{{ $help }}</p></article>
            @endforeach
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <form method="GET" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @if(auth()->user()->isSuperuser())
                    <label class="text-sm font-bold text-slate-700">Organization<select name="organization_id" class="mt-2 w-full rounded-xl border-slate-300 text-sm"><option value="">All organizations</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected((string)request('organization_id') === (string)$organization->id)>{{ $organization->name }}</option>@endforeach</select></label>
                @endif
                <label class="text-sm font-bold text-slate-700">Module<select name="module" class="mt-2 w-full rounded-xl border-slate-300 text-sm"><option value="">All modules</option>@foreach($modules as $module)<option value="{{ $module }}" @selected(request('module') === $module)>{{ str($module)->headline() }}</option>@endforeach</select></label>
                <label class="text-sm font-bold text-slate-700">Action<select name="action" class="mt-2 w-full rounded-xl border-slate-300 text-sm"><option value="">All actions</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ str($action)->headline() }}</option>@endforeach</select></label>
                <label class="text-sm font-bold text-slate-700">User<input name="user" value="{{ request('user') }}" placeholder="Name or email" class="mt-2 w-full rounded-xl border-slate-300 text-sm"></label>
                <label class="text-sm font-bold text-slate-700">Record or description<input name="record" value="{{ request('record') }}" placeholder="Tag, request, order…" class="mt-2 w-full rounded-xl border-slate-300 text-sm"></label>
                <label class="text-sm font-bold text-slate-700">From<input type="date" name="from" value="{{ request('from') }}" class="mt-2 w-full rounded-xl border-slate-300 text-sm"></label>
                <label class="text-sm font-bold text-slate-700">To<input type="date" name="to" value="{{ request('to') }}" class="mt-2 w-full rounded-xl border-slate-300 text-sm"></label>
                <div class="flex items-end gap-2"><button class="flex-1 rounded-xl bg-slate-900 px-4 py-3 text-sm font-bold text-white">Apply filters</button><a href="{{ route('settings.activity-log') }}" class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-bold text-slate-700">Reset</a></div>
            </form>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6"><h2 class="text-lg font-black text-slate-950">Audit timeline</h2><p class="mt-1 text-sm text-slate-500">Select details to inspect recorded changes and technical context.</p></div>
            <div class="divide-y divide-slate-200">
                @forelse($logs as $log)
                    @php $details = ['description'=>$log->description,'old_values'=>$log->old_values,'new_values'=>$log->new_values,'metadata'=>$log->metadata,'ip_address'=>$log->ip_address,'user_agent'=>$log->user_agent]; @endphp
                    <article class="grid gap-4 px-5 py-5 transition hover:bg-slate-50/70 sm:px-6 lg:grid-cols-[155px_minmax(170px,.7fr)_minmax(0,1.5fr)_auto] lg:items-center">
                        <div><p class="text-sm font-bold text-slate-900">{{ $log->created_at->format('d M Y') }}</p><p class="mt-1 text-xs text-slate-500">{{ $log->created_at->format('g:i:s A') }}</p></div>
                        <div class="min-w-0"><p class="truncate text-sm font-bold text-slate-900">{{ $log->user?->name ?? $log->name ?? 'System' }}</p><p class="truncate text-xs text-slate-500">{{ $log->user?->email ?: ($log->organization?->name ?? 'Platform activity') }}</p></div>
                        <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="rounded-full border px-2.5 py-1 text-[11px] font-bold {{ $actionTone($log->action) }}">{{ str($log->action)->headline() }}</span><span class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ str($log->module ?: 'Security')->headline() }}</span></div><p class="mt-2 text-sm font-semibold text-slate-800">{{ $log->record_label ?: str($log->action)->headline() }}</p>@if($log->description)<p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">{{ $log->description }}</p>@endif</div>
                        <button type="button" class="audit-details rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100" data-details='@json($details)'>Details</button>
                    </article>
                @empty
                    <div class="px-6 py-16 text-center"><p class="font-bold text-slate-900">No activity matches these filters.</p><p class="mt-2 text-sm text-slate-500">Adjust the filters or clear them to view the full timeline.</p></div>
                @endforelse
            </div>
            @if($logs->hasPages())<div class="border-t border-slate-200 px-5 py-4">{{ $logs->links() }}</div>@endif
        </section>
    </main>

    <div id="audit-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true"><div class="max-h-[85vh] w-full max-w-3xl overflow-hidden rounded-3xl bg-white shadow-2xl"><div class="flex items-center justify-between border-b border-slate-200 px-6 py-4"><div><h2 class="text-lg font-black text-slate-950">Activity details</h2><p class="text-xs text-slate-500">Recorded context and field changes</p></div><button id="audit-modal-close" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-bold">Close</button></div><pre id="audit-modal-content" class="max-h-[68vh] overflow-auto bg-slate-950 p-6 text-xs leading-6 text-emerald-100"></pre></div></div>

    <script>
        (() => {
            const modal = document.getElementById('audit-modal'); const content = document.getElementById('audit-modal-content');
            const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
            document.querySelectorAll('.audit-details').forEach(button => button.addEventListener('click', () => { try { content.textContent = JSON.stringify(JSON.parse(button.dataset.details), null, 2); } catch { content.textContent = button.dataset.details; } modal.classList.remove('hidden'); modal.classList.add('flex'); }));
            document.getElementById('audit-modal-close')?.addEventListener('click', close); modal?.addEventListener('click', event => { if (event.target === modal) close(); }); document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
        })();
    </script>
</x-app-layout>
