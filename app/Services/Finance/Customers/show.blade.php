<x-app-layout>
    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.customers.index') }}" class="text-sm text-slate-500 hover:underline">← Back to Customers</a>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @if($customer->on_credit_hold)
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-red-800">Warning Credit hold active</p>
                    @if($customer->credit_hold_reason)
                        <p class="text-xs text-red-700 mt-1">{{ $customer->credit_hold_reason }}</p>
                    @endif
                </div>
                @if(auth()->user()?->hasPermission('manage_customers'))
                    <form method="POST" action="{{ route('finance.customers.clear-hold', $customer) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="px-3 py-1.5 bg-white hover:bg-slate-50 border border-red-300 text-red-700 text-xs font-medium rounded-lg">Clear hold</button>
                    </form>
                @endif
            </div>
        @endif

        {{-- Header card --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide font-semibold">{{ $customer->category?->name ?? 'Uncategorised' }}</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-800">{{ $customer->name }}</h1>
                    @if($customer->legal_name && $customer->legal_name !== $customer->name)
                        <p class="text-sm text-slate-500">Legal: {{ $customer->legal_name }}</p>
                    @endif
                    <p class="font-mono text-xs text-slate-400 mt-1">{{ $customer->code }}</p>

                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        @if($customer->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">active</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-200 text-slate-700">inactive</span>
                        @endif
                        @if($customer->isCustomer() && $customer->isSupplier())
                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-blue-100 text-blue-800">customer + supplier</span>
                        @endif
                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-100 text-slate-700">{{ $customer->currency_code }}</span>
                    </div>
                </div>

                <div class="flex flex-col gap-2 min-w-[180px]">
                    @if(auth()->user()?->hasPermission('manage_customers'))
                        <a href="{{ route('finance.customers.edit', $customer) }}" class="text-center px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">Edit Details</a>
                        @if(! $customer->on_credit_hold)
                            <button type="button" onclick="document.getElementById('hold-form').classList.toggle('hidden')"
                                    class="px-4 py-2 bg-red-100 hover:bg-red-200 text-red-700 text-sm font-medium rounded-xl">Place on Hold</button>
                        @endif
                    @endif
                </div>
            </div>

            @if(auth()->user()?->hasPermission('manage_customers') && ! $customer->on_credit_hold)
                <form id="hold-form" method="POST" action="{{ route('finance.customers.place-on-hold', $customer) }}" class="mt-4 hidden">
                    @csrf
                    <label class="block text-sm font-medium text-slate-700 mb-1">Reason</label>
                    <textarea name="reason" required rows="2" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Why are we placing this customer on hold?"></textarea>
                    <button type="submit" class="mt-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl">Confirm Hold</button>
                </form>
            @endif
        </div>

        {{-- Quick facts grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Identity</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">TIN</dt><dd class="text-slate-800">{{ $customer->tax_id ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Reg No.</dt><dd class="text-slate-800">{{ $customer->registration_number ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Phone</dt><dd class="text-slate-800">{{ $customer->phone ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Email</dt><dd class="text-slate-800">{{ $customer->email ?? '—' }}</dd></div>
                </dl>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Finance Defaults</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Currency</dt><dd class="text-slate-800">{{ $customer->currency_code }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Payment Terms</dt><dd class="text-slate-800">{{ $customer->effectivePaymentTermsDays() !== null ? 'Net ' . $customer->effectivePaymentTermsDays() . ' days' : 'COD' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Credit Limit</dt><dd class="text-slate-800">@php $cl = $customer->effectiveCreditLimit(); @endphp{{ $cl !== null ? $customer->currency_code . ' ' . number_format((float) $cl, 2) : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Discount</dt><dd class="text-slate-800">{{ $customer->effectiveDiscountPercent() }}%</dd></div>
                </dl>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Sales Activity</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">First Sale</dt><dd class="text-slate-800">{{ $customer->first_sale_at?->format('Y-m-d') ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Last Sale</dt><dd class="text-slate-800">{{ $customer->last_sale_at?->format('Y-m-d') ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Lifetime Value</dt><dd class="text-slate-800">{{ $customer->currency_code }} {{ number_format((float) $customer->total_sales_value, 2) }}</dd></div>
                </dl>
                <p class="text-xs text-slate-400 mt-3 italic">These figures are populated by Phase 2c sales backfill.</p>
            </div>
        </div>

        {{-- Addresses --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden mb-4">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Addresses ({{ $customer->addresses->count() }})</h3>
                @if(auth()->user()?->hasPermission('manage_customers'))
                    <button type="button" onclick="document.getElementById('address-form').classList.toggle('hidden')"
                            class="text-emerald-700 hover:underline text-sm font-medium">+ Add address</button>
                @endif
            </div>

            @if(auth()->user()?->hasPermission('manage_customers'))
                <form id="address-form" method="POST" action="{{ route('finance.customers.addresses.store', $customer) }}" class="p-5 hidden border-b border-slate-100 bg-slate-50">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Type</label>
                            <select name="address_type" required class="w-full rounded-lg border-slate-200 text-sm">
                                <option value="billing">Billing</option>
                                <option value="shipping">Shipping</option>
                                <option value="pickup">Pickup</option>
                                <option value="office">Office</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Label (optional)</label>
                            <input type="text" name="label" class="w-full rounded-lg border-slate-200 text-sm" placeholder="e.g. Accra warehouse">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-slate-600 mb-1">Address Line 1</label>
                            <input type="text" name="address_line_1" required class="w-full rounded-lg border-slate-200 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">City</label>
                            <input type="text" name="city" class="w-full rounded-lg border-slate-200 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Ghana Post GPS</label>
                            <input type="text" name="ghana_post_gps" placeholder="GA-123-4567" class="w-full rounded-lg border-slate-200 text-sm">
                        </div>
                    </div>
                    <label class="inline-flex items-center gap-2 text-xs text-slate-600 mt-3">
                        <input type="checkbox" name="is_default" value="1" class="rounded border-slate-300">
                        Set as default for this type
                    </label>
                    <button type="submit" class="mt-3 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg">Add Address</button>
                </form>
            @endif

            @if($customer->addresses->isEmpty())
                <p class="p-8 text-center text-sm text-gray-500">No addresses yet.</p>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($customer->addresses as $a)
                        <div class="px-5 py-4 flex justify-between items-start">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs uppercase tracking-wide text-slate-500 font-semibold">{{ $a->address_type }}</span>
                                    @if($a->is_default)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-emerald-100 text-emerald-800">default</span>
                                    @endif
                                    @if($a->label)
                                        <span class="text-xs text-slate-400">— {{ $a->label }}</span>
                                    @endif
                                </div>
                                <p class="text-sm text-slate-700 mt-1">{{ $a->asSingleLine() }}</p>
                            </div>
                            @if(auth()->user()?->hasPermission('manage_customers'))
                                <form method="POST" action="{{ route('finance.customers.addresses.destroy', $a) }}" onsubmit="return confirm('Remove this address?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Contacts --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden mb-4">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Contacts ({{ $customer->contacts->count() }})</h3>
                @if(auth()->user()?->hasPermission('manage_customers'))
                    <button type="button" onclick="document.getElementById('contact-form').classList.toggle('hidden')"
                            class="text-emerald-700 hover:underline text-sm font-medium">+ Add contact</button>
                @endif
            </div>

            @if(auth()->user()?->hasPermission('manage_customers'))
                <form id="contact-form" method="POST" action="{{ route('finance.customers.contacts.store', $customer) }}" class="p-5 hidden border-b border-slate-100 bg-slate-50">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Name</label>
                            <input type="text" name="name" required class="w-full rounded-lg border-slate-200 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Role</label>
                            <input type="text" name="role" placeholder="e.g. Procurement Officer" class="w-full rounded-lg border-slate-200 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Phone</label>
                            <input type="text" name="phone" class="w-full rounded-lg border-slate-200 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">WhatsApp</label>
                            <input type="text" name="whatsapp" class="w-full rounded-lg border-slate-200 text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-slate-600 mb-1">Email</label>
                            <input type="email" name="email" class="w-full rounded-lg border-slate-200 text-sm">
                        </div>
                    </div>
                    <label class="inline-flex items-center gap-2 text-xs text-slate-600 mt-3">
                        <input type="checkbox" name="is_primary" value="1" class="rounded border-slate-300">
                        Mark as primary contact
                    </label>
                    <button type="submit" class="mt-3 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg">Add Contact</button>
                </form>
            @endif

            @if($customer->contacts->isEmpty())
                <p class="p-8 text-center text-sm text-gray-500">No contacts yet.</p>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($customer->contacts as $c)
                        <div class="px-5 py-4 flex justify-between items-start">
                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="font-medium text-slate-800">{{ $c->name }}</p>
                                    @if($c->is_primary)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-emerald-100 text-emerald-800">primary</span>
                                    @endif
                                </div>
                                @if($c->role)
                                    <p class="text-xs text-slate-500">{{ $c->role }}</p>
                                @endif
                                <div class="text-xs text-slate-600 mt-1 flex flex-wrap gap-3">
                                    @if($c->phone)<span>Phone {{ $c->phone }}</span>@endif
                                    @if($c->email)<span>Email {{ $c->email }}</span>@endif
                                    @if($c->whatsapp)<span>Chat {{ $c->whatsapp }}</span>@endif
                                </div>
                            </div>
                            @if(auth()->user()?->hasPermission('manage_customers'))
                                <form method="POST" action="{{ route('finance.customers.contacts.destroy', $c) }}" onsubmit="return confirm('Remove this contact?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Documents (placeholder for now — upload UI is Phase 2d) --}}
        @if($customer->documents->count() > 0)
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden mb-4">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Documents</h3>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($customer->documents as $d)
                        <div class="px-5 py-3 flex justify-between items-center text-sm">
                            <div>
                                <p class="font-medium">{{ str_replace('_', ' ', $d->document_type) }}</p>
                                @if($d->document_number)<p class="text-xs text-slate-500">#{{ $d->document_number }}</p>@endif
                            </div>
                            <div class="text-right">
                                @if($d->expiry_date)
                                    <p class="text-xs {{ $d->isExpired() ? 'text-red-600' : 'text-slate-500' }}">
                                        {{ $d->isExpired() ? 'Expired' : 'Expires' }} {{ $d->expiry_date->format('Y-m-d') }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Recent sales placeholder — wired up in Phase 2b --}}
        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-6 text-center">
            <p class="text-sm text-slate-500">Recent invoices and ledger activity will appear here once Phase 2b ships the invoice model.</p>
        </div>
    </div>
</x-app-layout>
