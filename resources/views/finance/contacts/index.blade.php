{{--
    Finance → People & Businesses (Phase 7, blueprint §27).

    One workspace over the counterparties the farm deals with, in the language
    the farm uses: who buys from us, who we buy from, and who owes what.

    The accountant's screens are not replaced — every row links out to the
    existing customer or supplier detail page, and "Full details" opens the
    complete form. This is the front door, not a replacement.
--}}
@php
    use App\Http\Controllers\Finance\ContactsController;

    $money = fn ($v) => number_format((float) $v, 2);

    $tabs = [
        ContactsController::TAB_CUSTOMERS => 'Who buys from us',
        ContactsController::TAB_SUPPLIERS => 'Who we buy from',
        ContactsController::TAB_BALANCES  => 'Who owes what',
    ];
@endphp

<x-app-layout>
    <main class="min-h-screen bg-slate-50/70 py-4">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">People &amp; Businesses</h1>
                    <p class="mt-1 text-sm text-slate-600">Everyone the farm buys from and sells to.</p>
                </div>
            </div>

            @if(session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            {{-- Tabs. Horizontally scrollable so three tabs still work on a phone. --}}
            <div class="flex gap-2 overflow-x-auto pb-1">
                @foreach($tabs as $key => $label)
                    <a href="{{ route('finance.contacts.index', ['tab' => $key]) }}"
                       class="whitespace-nowrap px-4 py-2 rounded-xl text-sm font-medium border
                              {{ $tab === $key ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-600 border-slate-200 hover:border-slate-400' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            @if($tab === ContactsController::TAB_BALANCES)
                {{-- Balances: read from the same ageing services the accountant's reports use. --}}
                <div class="grid md:grid-cols-2 gap-6">
                    <section class="rounded-2xl border border-slate-200 bg-white p-5">
                        <div class="flex items-baseline justify-between">
                            <h2 class="font-semibold text-slate-800">Owed to us</h2>
                            <p class="text-xl font-bold text-emerald-700">GHS {{ $money($owedToUsTotal) }}</p>
                        </div>
                        <ul class="mt-4 divide-y divide-slate-100 text-sm">
                            @forelse($owedToUs as $line)
                                <li class="py-2.5 flex items-center justify-between gap-3">
                                    <a href="{{ route('finance.customers.show', $line->counterparty) }}" class="text-slate-700 hover:underline">
                                        {{ $line->counterparty->name }}
                                        @if($line->hasSevereOverdue())
                                            <span class="ml-1 px-1.5 py-0.5 rounded text-xs bg-rose-50 text-rose-700">90+ days</span>
                                        @endif
                                    </a>
                                    <span class="font-medium tabular-nums">{{ $money($line->buckets['total'] ?? '0') }}</span>
                                </li>
                            @empty
                                <li class="py-4 text-slate-400">Nobody owes the farm anything.</li>
                            @endforelse
                        </ul>
                    </section>

                    {{-- What the farm owes is a narrower permission than what it is
                         owed: finance/ageing/ap requires view_ap_ageing while
                         finance/ageing/ar requires only view_finance. A friendly
                         summary that showed both to everyone would route around
                         that distinction. --}}
                    <section class="rounded-2xl border border-slate-200 bg-white p-5">
                        <div class="flex items-baseline justify-between">
                            <h2 class="font-semibold text-slate-800">We owe</h2>
                            @if($canSeePayables)
                                <p class="text-xl font-bold text-rose-700">GHS {{ $money($owedByUsTotal) }}</p>
                            @endif
                        </div>
                        @if($canSeePayables)
                            <ul class="mt-4 divide-y divide-slate-100 text-sm">
                                @forelse($owedByUs as $line)
                                    <li class="py-2.5 flex items-center justify-between gap-3">
                                        <a href="{{ route('finance.suppliers.show', $line->counterparty) }}" class="text-slate-700 hover:underline">
                                            {{ $line->counterparty->name }}
                                            @if($line->hasSevereOverdue())
                                                <span class="ml-1 px-1.5 py-0.5 rounded text-xs bg-rose-50 text-rose-700">90+ days</span>
                                            @endif
                                        </a>
                                        <span class="font-medium tabular-nums">{{ $money($line->buckets['total'] ?? '0') }}</span>
                                    </li>
                                @empty
                                    <li class="py-4 text-slate-400">The farm owes nobody anything.</li>
                                @endforelse
                            </ul>
                        @else
                            <p class="mt-4 text-sm text-slate-400">
                                You do not have access to what the farm owes its suppliers.
                            </p>
                        @endif
                    </section>
                </div>

                <p class="text-xs text-slate-400">
                    The same figures the ageing reports show, from the same calculation.
                    @canopen('finance.ageing.ar')<a class="underline" href="{{ route('finance.ageing.ar') }}">Full ageing report</a>@endcanopen
                </p>
            @else
                <form method="GET" class="flex flex-wrap gap-2">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <input type="text" name="q" value="{{ $search }}" placeholder="Search by name, code, phone or email"
                           class="flex-1 min-w-[220px] rounded-xl border-slate-300 text-sm">
                    <button type="submit" class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-sm">Search</button>
                </form>

                <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
                    <ul class="divide-y divide-slate-100">
                        @forelse($contacts as $contact)
                            @php
                                $detailRoute = $tab === ContactsController::TAB_SUPPLIERS
                                    ? route('finance.suppliers.show', $contact)
                                    : route('finance.customers.show', $contact);
                            @endphp
                            <li>
                                <a href="{{ $detailRoute }}" class="flex items-center justify-between gap-3 px-4 py-3.5 hover:bg-slate-50">
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-800 truncate">
                                            {{ $contact->name }}
                                            @if($contact->type === \App\Models\Counterparty::TYPE_BOTH)
                                                <span class="ml-1 px-1.5 py-0.5 rounded text-xs bg-slate-100 text-slate-600">buys &amp; sells</span>
                                            @endif
                                            @if($contact->on_credit_hold)
                                                <span class="ml-1 px-1.5 py-0.5 rounded text-xs bg-rose-50 text-rose-700">on hold</span>
                                            @endif
                                        </p>
                                        <p class="text-xs text-slate-500 truncate">
                                            {{ $contact->code }}@if($contact->phone) · {{ $contact->phone }}@endif @if($contact->email) · {{ $contact->email }}@endif
                                        </p>
                                    </div>
                                    <span class="text-slate-300">›</span>
                                </a>
                            </li>
                        @empty
                            <li class="px-4 py-10 text-center text-sm text-slate-400">
                                @if($search !== '')
                                    Nobody matches “{{ $search }}”.
                                @else
                                    Nobody here yet. Add the first below.
                                @endif
                            </li>
                        @endforelse
                    </ul>
                </div>

                <div>{{ $contacts->links() }}</div>
            @endif

            {{-- Guided creation. Name, role, and a way to reach them; everything
                 else takes the defaults the services already apply. --}}
            @if($canAddCustomer || $canAddSupplier)
                <section class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="font-semibold text-slate-800">Add someone</h2>
                    <p class="text-xs text-slate-500 mt-1">
                        A name and a way to reach them is enough. Terms, credit limits and tax
                        details can be filled in later on their page.
                    </p>

                    <form method="POST" action="{{ route('finance.contacts.store') }}" class="mt-4 grid sm:grid-cols-12 gap-3 items-end">
                        @csrf
                        <label class="sm:col-span-4 block text-sm">
                            <span class="text-slate-600">Name</span>
                            <input type="text" name="name" required maxlength="255" value="{{ old('name') }}"
                                   class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="sm:col-span-3 block text-sm">
                            <span class="text-slate-600">They…</span>
                            <select name="role" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                                @if($canAddCustomer)
                                    <option value="customer" @selected(old('role') === 'customer')>buy from us</option>
                                @endif
                                @if($canAddSupplier)
                                    <option value="supplier" @selected(old('role') === 'supplier')>sell to us</option>
                                @endif
                                @if($canAddCustomer && $canAddSupplier)
                                    <option value="both" @selected(old('role') === 'both')>both</option>
                                @endif
                            </select>
                        </label>
                        <label class="sm:col-span-2 block text-sm">
                            <span class="text-slate-600">Phone</span>
                            <input type="text" name="phone" maxlength="50" value="{{ old('phone') }}"
                                   class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="sm:col-span-2 block text-sm">
                            <span class="text-slate-600">Email</span>
                            <input type="email" name="email" maxlength="100" value="{{ old('email') }}"
                                   class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </label>
                        <div class="sm:col-span-1">
                            <button type="submit" class="w-full px-3 py-2 rounded-xl bg-slate-800 text-white text-sm">Add</button>
                        </div>
                    </form>

                    <p class="mt-3 text-xs text-slate-400">
                        Need terms, credit limits or tax details up front?
                        @if($canAddCustomer)
                            <a class="underline" href="{{ route('finance.customers.create') }}">Full customer form</a>
                        @endif
                        @if($canAddCustomer && $canAddSupplier) · @endif
                        @if($canAddSupplier)
                            <a class="underline" href="{{ route('finance.suppliers.create') }}">Full supplier form</a>
                        @endif
                    </p>
                </section>
            @endif

        </div>
    </main>
</x-app-layout>
