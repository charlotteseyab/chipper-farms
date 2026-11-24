{{-- What needs someone to act now, most urgent first (FeedTodayService). --}}
        @php
            $toneBox = ['alert' => 'border-red-200 bg-red-50', 'warn' => 'border-amber-200 bg-amber-50'];
            $toneDot = ['alert' => 'bg-red-500', 'warn' => 'bg-amber-500'];
            $canRecord = auth()->user()->hasPermission('manage_feed_issues');
        @endphp
        <section aria-labelledby="needs-doing" class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 id="needs-doing" class="text-lg font-bold text-slate-900">Needs doing @if($needs)<span class="ml-1 rounded-full bg-slate-900 px-2 py-0.5 text-xs font-semibold text-white">{{ count($needs) }}</span>@endif</h2>
            @forelse($needs as $need)
                <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3 {{ $toneBox[$need['tone']] ?? 'border-slate-100' }}">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $toneDot[$need['tone']] ?? 'bg-slate-400' }}" aria-hidden="true"></span>
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900">{{ $need['title'] }}</p>
                            <p class="text-sm text-slate-600">{{ $need['detail'] }}</p>
                        </div>
                    </div>
                    @if(! str_starts_with($need['url'], route('feed.issues.create')) && ($need['action'] !== 'Record feeding' || $canRecord))
                        <a href="{{ $need['url'] }}" class="shrink-0 rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700">{{ $need['action'] }}</a>
                    @endif
                </div>
            @empty
                <p class="mt-2 rounded-xl bg-emerald-50 p-3 text-sm font-medium text-emerald-800">Nothing needs doing right now: every stage is fed, feed and ingredients are in stock, and no mix is waiting.</p>
            @endforelse
        </section>
