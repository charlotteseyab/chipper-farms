<x-app-layout>
    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="mb-4 flex items-center justify-between">
            <a href="{{ route('finance.suppliers.index') }}" class="text-sm text-slate-500 hover:underline">← Back to Suppliers</a>

            @if(auth()->user()?->hasPermission('manage_suppliers'))
                <div class="flex gap-2">
                    <a href="{{ route('finance.suppliers.edit', $supplier) }}"
                       class="inline-flex items-center px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">
                        Edit
                    </a>
                    @if($supplier->is_active)
                        <button type="button" onclick="confirmDeactivate()"
                                class="inline-flex items-center px-3 py-1.5 bg-amber-100 hover:bg-amber-200 text-amber-800 text-sm font-medium rounded-xl">
                            Deactivate
                        </button>
                    @endif
                </div>
            @endif
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif

        @unless($supplier->is_active)
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <strong>Inactive.</strong> This supplier is no longer available in vendor invoice creation. Historical records preserved.
            </div>
        @endunless

        {{-- Header card --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide font-semibold">Supplier</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-800">{{ $supplier->name }}</h1>
                    <p class="text-sm text-slate-500 font-mono">{{ $supplier->code }}</p>
                    @if($supplier->legal_name && $supplier->legal_name !== $supplier->name)
                        <p class="text-sm text-slate-600 mt-1 italic">{{ $supplier->legal_name }}</p>
                    @endif
                    <div class="mt-2 flex flex-wrap gap-2 items-center text-xs">
                        @if($supplier->supplierCategory)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-medium">{{ $supplier->supplierCategory->name }}</span>
                        @endif
                        @if($supplier->type === 'both')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-purple-100 text-purple-800 font-medium">customer + supplier</span>
                        @endif
                        @if($supplier->withholding_exempt)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 font-medium">WHT exempt</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            {{-- Identity + contact --}}
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Identity &amp; Contact</h3>
                <dl class="grid grid-cols-2 gap-y-2 text-sm">
                    @if($supplier->tax_id)<dt class="text-slate-500">Tax ID</dt><dd class="text-slate-800 font-mono text-xs">{{ $supplier->tax_id }}</dd>@endif
                    @if($supplier->registration_number)<dt class="text-slate-500">Reg. Number</dt><dd class="text-slate-800 font-mono text-xs">{{ $supplier->registration_number }}</dd>@endif
                    @if($supplier->phone)<dt class="text-slate-500">Phone</dt><dd class="text-slate-800">{{ $supplier->phone }}</dd>@endif
                    @if($supplier->email)<dt class="text-slate-500">Email</dt><dd class="text-slate-800">{{ $supplier->email }}</dd>@endif
                    @if($supplier->primary_contact_name)<dt class="text-slate-500">Contact</dt><dd class="text-slate-800">{{ $supplier->primary_contact_name }}</dd>@endif
                    @if($supplier->address_line_1)
                        <dt class="text-slate-500">Address</dt>
                        <dd class="text-slate-800 text-xs">
                            {{ $supplier->address_line_1 }}
                            @if($supplier->city), {{ $supplier->city }}@endif
                            @if($supplier->region), {{ $supplier->region }}@endif
                        </dd>
                    @endif
                </dl>
            </div>

            {{-- Finance defaults --}}
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Finance Defaults</h3>
                <dl class="grid grid-cols-2 gap-y-2 text-sm">
                    <dt class="text-slate-500">Currency</dt><dd class="text-slate-800 font-mono">{{ $supplier->currency_code }}</dd>
                    <dt class="text-slate-500">Payment Terms</dt><dd class="text-slate-800">{{ $supplier->payment_terms_days ?? '—' }} days</dd>
                    <dt class="text-slate-500">Default Expense GL</dt>
                    <dd class="text-slate-800 text-xs">
                        @if($supplier->defaultExpenseAccount)
                            <span class="font-mono">{{ $supplier->defaultExpenseAccount->code }}</span> {{ $supplier->defaultExpenseAccount->name }}
                        @else
                            <span class="text-slate-400">via category</span>
                        @endif
                    </dd>
                    <dt class="text-slate-500">Default AP Method</dt>
                    <dd class="text-slate-800 text-xs">{{ $supplier->defaultPaymentMethodForAp?->name ?? '—' }}</dd>
                    <dt class="text-slate-500">Withholding</dt>
                    <dd class="text-slate-800">
                        @if($supplier->withholding_exempt)
                            <span class="text-emerald-700">exempt</span>
                        @elseif($supplier->withholding_percent !== null)
                            {{ $supplier->withholding_percent }}%
                        @else
                            <span class="text-slate-400">via category ({{ $supplier->effectiveWithholdingPercent() }}%)</span>
                        @endif
                    </dd>
                </dl>
            </div>

            {{-- Banking --}}
            @if($supplier->bank_account_number || $supplier->momo_number)
                <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5 lg:col-span-2">
                    <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Banking &amp; Mobile Money</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if($supplier->bank_account_number)
                            <div>
                                <p class="text-xs uppercase text-slate-500 mb-1 font-semibold">Bank Account</p>
                                <p class="text-sm font-medium text-slate-800">{{ $supplier->bank_account_name ?? $supplier->name }}</p>
                                <p class="text-sm text-slate-600 font-mono">{{ $supplier->bank_account_number }}</p>
                                <p class="text-xs text-slate-500">{{ $supplier->bank_name }}@if($supplier->bank_branch), {{ $supplier->bank_branch }}@endif</p>
                                @if($supplier->bank_swift)<p class="text-xs text-slate-500">SWIFT: <span class="font-mono">{{ $supplier->bank_swift }}</span></p>@endif
                            </div>
                        @endif
                        @if($supplier->momo_number)
                            <div>
                                <p class="text-xs uppercase text-slate-500 mb-1 font-semibold">Mobile Money</p>
                                <p class="text-sm font-medium text-slate-800">{{ $supplier->momo_account_name ?? $supplier->name }}</p>
                                <p class="text-sm text-slate-600 font-mono">{{ $supplier->momo_number }}</p>
                                <p class="text-xs text-slate-500">{{ $supplier->momo_provider }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Activity --}}
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5 lg:col-span-2">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Activity</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                    <div>
                        <p class="text-xs uppercase text-slate-500">Total Spend</p>
                        <p class="text-lg font-semibold text-slate-800 mt-1">
                            {{ $supplier->currency_code }} {{ number_format((float) $supplier->total_purchases_value, 2) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-slate-500">First Purchase</p>
                        <p class="text-sm text-slate-700 mt-1">
                            {{ $supplier->first_purchase_at?->format('Y-m-d') ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-slate-500">Last Purchase</p>
                        <p class="text-sm text-slate-700 mt-1">
                            {{ $supplier->last_purchase_at?->format('Y-m-d') ?? '—' }}
                        </p>
                    </div>
                </div>

                <p class="text-xs text-slate-500 mt-4 italic">
                    Vendor invoice listing for this supplier becomes available in Phase 3b.
                </p>
            </div>
        </div>

        @if($supplier->notes || $supplier->internal_notes)
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                @if($supplier->notes)
                    <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-2">Notes</h3>
                    <p class="text-sm text-slate-700 whitespace-pre-line">{{ $supplier->notes }}</p>
                @endif
                @if($supplier->internal_notes)
                    <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mt-4 mb-2">Internal Notes</h3>
                    <p class="text-sm text-slate-600 whitespace-pre-line italic">{{ $supplier->internal_notes }}</p>
                @endif
            </div>
        @endif
    </div>

    @if($supplier->is_active && auth()->user()?->hasPermission('manage_suppliers'))
        <form id="deactivate-form" method="POST" action="{{ route('finance.suppliers.deactivate', $supplier) }}" class="hidden">
            @csrf
            <input type="hidden" name="reason" value="">
        </form>
    @endif

    <script>
        function confirmDeactivate() {
            const reason = prompt('Reason for deactivating? (optional)');
            if (reason === null) return;
            const form = document.getElementById('deactivate-form');
            form.querySelector('input[name="reason"]').value = (reason || '').trim();
            form.submit();
        }
    </script>
</x-app-layout>
