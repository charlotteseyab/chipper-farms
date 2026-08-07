<x-app-layout>
    <main class="min-h-screen bg-slate-50/70 py-4">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Header --}}
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Modules &amp; Features</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Choose what {{ $organization?->name ?? 'your farm' }} uses. Switching something off
                    hides it from menus — it never deletes your records, and anything already in progress
                    can still be finished.
                </p>
            </div>

            @if (session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Presets --}}
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                    <h2 class="text-base font-semibold text-slate-900">Quick setup</h2>
                    <p class="mt-0.5 text-sm text-slate-600">
                        @if ($currentPreset)
                            You are on <span class="font-medium text-slate-900">{{ $presets[$currentPreset]['label'] }}</span>@if (count($presetDrift)), with {{ count($presetDrift) }} manual change{{ count($presetDrift) === 1 ? '' : 's' }}@endif.
                        @else
                            Pick a starting point, then fine-tune below.
                        @endif
                    </p>
                </div>

                <div class="divide-y divide-slate-100">
                    @foreach ($presets as $code => $preset)
                        <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-slate-900">{{ $preset['label'] }}</span>
                                    @if ($preset['overlay'])
                                        <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700">
                                            Add-on
                                        </span>
                                    @endif
                                    @if ($currentPreset === $code)
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                            Current
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm text-slate-600">{{ $preset['description'] }}</p>
                            </div>

                            <form method="POST" action="{{ route('settings.modules.preset') }}" class="shrink-0">
                                @csrf
                                <input type="hidden" name="preset" value="{{ $code }}">
                                <button type="submit"
                                        class="w-full rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 sm:w-auto">
                                    {{ $preset['overlay'] ? 'Add these' : 'Use this setup' }}
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>

                <p class="border-t border-slate-100 bg-slate-50/60 px-5 py-3 text-xs text-slate-500 sm:px-6">
                    Applying a setup only switches things <em>on</em>. Nothing you are already using is
                    turned off.
                </p>
            </section>

            {{-- Feature groups --}}
            @foreach ($grouped as $domain => $categories)
                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                        <h2 class="text-base font-semibold capitalize text-slate-900">{{ $domain }}</h2>
                    </div>

                    @foreach (['essential', 'advanced'] as $category)
                        @continue (empty($categories[$category]))

                        <div class="border-b border-slate-100 bg-slate-50/60 px-5 py-2 sm:px-6">
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ $category === 'essential' ? 'Everyday' : 'Advanced' }}
                            </span>
                        </div>

                        <div class="divide-y divide-slate-100">
                            @foreach ($categories[$category] as $key => $item)
                                <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                                    <div class="min-w-0 sm:pr-6">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-medium text-slate-900">
                                                {{ $item['definition']['label'] }}
                                            </span>
                                            @if ($item['enabled'] && ! $item['usable'])
                                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                                                    Needs setup
                                                </span>
                                            @endif
                                        </div>

                                        <p class="mt-1 text-sm text-slate-600">
                                            {{ $item['definition']['description'] }}
                                        </p>

                                        @if ($item['requires'])
                                            <p class="mt-1.5 text-xs text-slate-500">
                                                Needs: {{ collect($item['requires'])->map(fn ($k) => \App\Support\Features\FeatureCatalog::definition($k)['label'])->join(', ') }}
                                            </p>
                                        @endif

                                        @if (! $item['enabled'] && $item['dependents'])
                                            <p class="mt-1.5 text-xs text-slate-500">
                                                Turning this on also enables what depends on it.
                                            </p>
                                        @elseif ($item['enabled'] && $item['dependents'])
                                            <p class="mt-1.5 text-xs text-amber-700">
                                                Turning this off will also switch off
                                                {{ collect($item['dependents'])->map(fn ($k) => \App\Support\Features\FeatureCatalog::definition($k)['label'])->join(', ') }}.
                                            </p>
                                        @endif

                                        @if ($item['row']?->enabled_at && $item['enabled'])
                                            <p class="mt-1.5 text-xs text-slate-400">
                                                On since {{ $item['row']->enabled_at->format('j M Y') }}@if ($item['row']->enabledBy), set by {{ $item['row']->enabledBy->name }}@endif.
                                            </p>
                                        @elseif ($item['row']?->disabled_at && ! $item['enabled'])
                                            <p class="mt-1.5 text-xs text-slate-400">
                                                Off since {{ $item['row']->disabled_at->format('j M Y') }}@if ($item['row']->disabledBy), set by {{ $item['row']->disabledBy->name }}@endif.
                                            </p>
                                        @endif
                                    </div>

                                    <form method="POST" action="{{ route('settings.modules.update') }}" class="shrink-0">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="feature_key" value="{{ $key }}">
                                        <input type="hidden" name="enabled" value="{{ $item['enabled'] ? 0 : 1 }}">
                                        <button type="submit"
                                                @class([
                                                    'w-full rounded-xl px-4 py-2 text-sm font-medium sm:w-auto',
                                                    'bg-emerald-600 text-white hover:bg-emerald-700' => ! $item['enabled'],
                                                    'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' => $item['enabled'],
                                                ])
                                                @if ($item['enabled'] && $item['dependents'])
                                                    onclick="return confirm('This will also switch off {{ collect($item['dependents'])->map(fn ($k) => \App\Support\Features\FeatureCatalog::definition($k)['label'])->join(', ') }}. Continue?')"
                                                @endif>
                                            {{ $item['enabled'] ? 'Turn off' : 'Turn on' }}
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </section>
            @endforeach

            <p class="pb-8 text-xs text-slate-500">
                Switching a module off hides its menus and pages. Your records stay exactly where they
                are, and automatic bookkeeping keeps running in the background.
            </p>
        </div>
    </main>
</x-app-layout>
