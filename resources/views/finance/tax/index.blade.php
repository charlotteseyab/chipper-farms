<x-app-layout>
    <main class="min-h-screen bg-slate-50/70 py-4">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Tax</h1>
                <p class="mt-1 text-sm text-slate-600">
                    What your business charges on sales and pays on purchases. Set this up with
                    your accountant.
                </p>
            </div>

            @if (session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            {{-- Existing profiles --}}
            @forelse ($profiles as $profile)
                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-slate-900">{{ $profile->name }}</span>
                                @if ($profile->is_default_sales)
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Default for sales</span>
                                @endif
                                @if ($profile->is_default_purchase)
                                    <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700">Default for purchases</span>
                                @endif
                                @if ($profile->effective_to && $profile->effective_to->isPast())
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Historical</span>
                                @endif
                            </div>
                            @if ($profile->description)
                                <p class="mt-1 text-sm text-slate-600">{{ $profile->description }}</p>
                            @endif
                            <p class="mt-1 text-xs text-slate-400">
                                {{ $profile->code }} · from {{ $profile->effective_from?->format('j M Y') }}@if ($profile->effective_to) to {{ $profile->effective_to->format('j M Y') }}@endif
                                · prices are {{ $profile->price_mode }} of tax
                            </p>
                        </div>

                        {{-- Live preview from the real calculator --}}
                        <div class="shrink-0 rounded-2xl bg-slate-50 px-4 py-3 sm:text-right">
                            <p class="text-xs text-slate-500">On GHS 100.00</p>
                            <p class="text-lg font-semibold text-slate-900">
                                +{{ number_format((float) $previews[$profile->id]->totalTax, 2) }}
                            </p>
                            <p class="text-xs text-slate-500">
                                total {{ number_format((float) $previews[$profile->id]->grossTotal, 2) }}
                            </p>
                        </div>
                    </div>

                    @if ($profile->components->count())
                        <ul class="divide-y divide-slate-100">
                            @foreach ($profile->components as $component)
                                <li class="flex items-center justify-between px-5 py-3 sm:px-6">
                                    <div class="min-w-0">
                                        <span class="text-sm text-slate-800">{{ $component->label }}</span>
                                        <span class="ml-2 text-xs text-slate-500">
                                            @if ($component->isPercent())
                                                {{ rtrim(rtrim((string) $component->rate, '0'), '.') }}%
                                            @else
                                                GHS {{ number_format((float) $component->rate, 2) }} fixed
                                            @endif
                                            @if ($component->isCompound())
                                                · on top of the charges above
                                            @endif
                                        </span>
                                    </div>
                                    <form method="POST" action="{{ route('finance.tax.components.destroy', [$profile, $component]) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-rose-600 hover:text-rose-700">Remove</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @elseif ($profile->treatment === 'standard')
                        <p class="px-5 py-3 text-sm text-amber-700 sm:px-6">
                            This charges nothing yet — add what it should charge below.
                        </p>
                    @endif

                    {{-- Add a charge --}}
                    @if ($profile->treatment === 'standard')
                        <form method="POST" action="{{ route('finance.tax.components.store', $profile) }}"
                              class="border-t border-slate-100 bg-slate-50/60 px-5 py-4 sm:px-6">
                            @csrf
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-5">
                                <input type="text" name="label" placeholder="Name, e.g. VAT" required maxlength="255"
                                       class="rounded-xl border-slate-300 text-sm sm:col-span-2">
                                <input type="text" name="code" placeholder="CODE" required maxlength="40"
                                       class="rounded-xl border-slate-300 text-sm uppercase">
                                <select name="rate_type" class="rounded-xl border-slate-300 text-sm">
                                    <option value="percent">% of the amount</option>
                                    <option value="fixed">Fixed GHS</option>
                                </select>
                                <input type="number" name="rate" step="0.0001" min="0" placeholder="15" required
                                       class="rounded-xl border-slate-300 text-sm">
                            </div>
                            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <label class="flex items-center gap-2 text-xs text-slate-600">
                                    <input type="checkbox" name="base_mode" value="compound_on_previous"
                                           class="rounded border-slate-300">
                                    Charge this on top of the other charges, not just the base amount
                                </label>
                                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                                    Add charge
                                </button>
                            </div>
                        </form>
                    @endif

                    <div class="flex flex-wrap gap-2 border-t border-slate-100 px-5 py-3 sm:px-6">
                        @unless ($profile->is_default_sales)
                            <form method="POST" action="{{ route('finance.tax.profiles.default', $profile) }}">
                                @csrf <input type="hidden" name="side" value="sales">
                                <button class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50">
                                    Use for sales
                                </button>
                            </form>
                        @endunless
                        @unless ($profile->is_default_purchase)
                            <form method="POST" action="{{ route('finance.tax.profiles.default', $profile) }}">
                                @csrf <input type="hidden" name="side" value="purchase">
                                <button class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50">
                                    Use for purchases
                                </button>
                            </form>
                        @endunless
                    </div>
                </section>
            @empty
                <div class="rounded-3xl border border-amber-200 bg-amber-50/60 px-5 py-4 text-sm text-amber-900 sm:px-6">
                    No tax is set up yet. Until you add one, sales will not carry tax — which is
                    not the same as being exempt.
                </div>
            @endforelse

            {{-- New profile --}}
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                    <h2 class="text-base font-semibold text-slate-900">Add a tax</h2>
                </div>
                <form method="POST" action="{{ route('finance.tax.profiles.store') }}" class="space-y-4 px-5 py-4 sm:px-6">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Name</label>
                            <input type="text" name="name" required maxlength="255" value="{{ old('name') }}"
                                   placeholder="e.g. Standard VAT"
                                   class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Short code</label>
                            <input type="text" name="code" required maxlength="40" value="{{ old('code') }}"
                                   placeholder="STANDARD_VAT"
                                   class="mt-1 w-full rounded-xl border-slate-300 text-sm uppercase">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700">How is it treated?</label>
                        <select name="treatment" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            @foreach ($treatments as $value => $label)
                                <option value="{{ $value }}" @selected(old('treatment') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Applies to</label>
                            <select name="applies_to" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                                <option value="both">Sales and purchases</option>
                                <option value="sale">Sales only</option>
                                <option value="purchase">Purchases only</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Prices you enter</label>
                            <select name="price_mode" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                                <option value="exclusive">Exclude tax</option>
                                <option value="inclusive">Already include tax</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Starts</label>
                            <input type="date" name="effective_from" required
                                   value="{{ old('effective_from', now()->toDateString()) }}"
                                   class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </div>
                    </div>

                    <p class="text-xs text-slate-500">
                        The start date matters: a sale dated before it will use whatever tax
                        applied then, so old records keep making sense when rates change.
                    </p>

                    <button type="submit" class="w-full rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-slate-800 sm:w-auto">
                        Add tax
                    </button>
                </form>
            </section>
        </div>
    </main>
</x-app-layout>
