<x-app-layout>
    <div class="max-w-3xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.customers.show', $customer) }}" class="text-sm text-slate-500 hover:underline">← Back to {{ $customer->name }}</a>
        </div>

        <h1 class="text-2xl font-bold text-gray-800 mb-2">Credit Control</h1>
        <p class="text-sm text-slate-500 mb-1">{{ $customer->name }}</p>
        <p class="text-xs text-slate-400 font-mono mb-6">{{ $customer->code }}</p>

        @if($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        {{-- Current state summary --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5 mb-4">
            <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Current Status</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                <div>
                    <dt class="text-xs text-slate-500 uppercase tracking-wide">Credit Limit</dt>
                    <dd class="text-base font-semibold text-slate-800 mt-1">
                        @if($customer->credit_limit)
                            {{ $customer->currency_code ?? 'GHS' }} {{ number_format((float) $customer->credit_limit, 2) }}
                        @else
                            <span class="text-slate-400">unset</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500 uppercase tracking-wide">Hold Mode</dt>
                    <dd class="mt-1">
                        @php
                            $mode = $customer->credit_hold_mode ?? 'warn';
                            $modeClasses = [
                                'none'  => 'bg-slate-100 text-slate-700',
                                'warn'  => 'bg-amber-100 text-amber-800',
                                'block' => 'bg-red-100 text-red-800',
                            ];
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $modeClasses[$mode] ?? '' }}">{{ $mode }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500 uppercase tracking-wide">Last Updated</dt>
                    <dd class="text-sm text-slate-700 mt-1">
                        @if($customer->credit_hold_set_at)
                            {{ \Illuminate\Support\Carbon::parse($customer->credit_hold_set_at)->format('Y-m-d H:i') }}
                            @if($customer->credit_hold_set_by_user_id)
                                @php $setter = \App\Models\User::find($customer->credit_hold_set_by_user_id); @endphp
                                @if($setter)
                                    <p class="text-xs text-slate-400 mt-0.5">by {{ $setter->name }}</p>
                                @endif
                            @endif
                        @else
                            <span class="text-slate-400">never</span>
                        @endif
                    </dd>
                </div>
            </dl>

            @if($customer->credit_hold_reason)
                <div class="mt-3 pt-3 border-t border-slate-100">
                    <dt class="text-xs text-slate-500 uppercase tracking-wide">Current Reason</dt>
                    <dd class="text-sm text-slate-700 mt-1 italic">{{ $customer->credit_hold_reason }}</dd>
                </div>
            @endif
        </div>

        {{-- Edit form --}}
        <form method="POST" action="{{ route('finance.customers.credit-hold.update', $customer) }}">
            @csrf

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Update Settings</h2>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Credit Limit</label>
                    <input type="number" step="0.01" min="0" name="credit_limit"
                           value="{{ old('credit_limit', $customer->credit_limit) }}"
                           placeholder="Leave blank for no limit"
                           class="w-full rounded-xl border-slate-200 text-sm text-right">
                    <p class="text-xs text-slate-500 mt-1">
                        Maximum outstanding AR balance allowed before the hold mode applies. Leave blank to skip credit checks for this customer.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Hold Mode <span class="text-red-500">*</span></label>
                    <div class="space-y-2">
                        @php $current = old('credit_hold_mode', $customer->credit_hold_mode ?? 'warn'); @endphp

                        <label class="flex items-start gap-3 p-3 rounded-xl border-2 cursor-pointer transition {{ $current === 'none' ? 'border-slate-400 bg-slate-50' : 'border-slate-100 hover:bg-slate-50' }}">
                            <input type="radio" name="credit_hold_mode" value="none" class="mt-0.5" @checked($current === 'none')>
                            <div class="flex-1">
                                <span class="block text-sm font-semibold text-slate-700">None</span>
                                <span class="block text-xs text-slate-500 mt-0.5">No credit checks. Sales proceed regardless of outstanding balance.</span>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-3 rounded-xl border-2 cursor-pointer transition {{ $current === 'warn' ? 'border-amber-400 bg-amber-50' : 'border-slate-100 hover:bg-slate-50' }}">
                            <input type="radio" name="credit_hold_mode" value="warn" class="mt-0.5" @checked($current === 'warn')>
                            <div class="flex-1">
                                <span class="block text-sm font-semibold text-amber-800">Warn</span>
                                <span class="block text-xs text-slate-500 mt-0.5">If a sale would push the customer over their credit limit, show a warning to operations. The sale still proceeds.</span>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-3 rounded-xl border-2 cursor-pointer transition {{ $current === 'block' ? 'border-red-400 bg-red-50' : 'border-slate-100 hover:bg-slate-50' }}">
                            <input type="radio" name="credit_hold_mode" value="block" class="mt-0.5" @checked($current === 'block')>
                            <div class="flex-1">
                                <span class="block text-sm font-semibold text-red-800">Block</span>
                                <span class="block text-xs text-slate-500 mt-0.5">Refuse new sales when the customer is over their credit limit. Admin override required.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Reason / Notes</label>
                    <textarea name="credit_hold_reason" rows="3"
                              placeholder="Why is this hold mode being set? Used for credit-control audit history."
                              class="w-full rounded-xl border-slate-200 text-sm">{{ old('credit_hold_reason', $customer->credit_hold_reason) }}</textarea>
                </div>
            </div>

            @if(! config('finance.credit_control.enforce_in_pig_sales'))
                <div class="mt-4 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                    <p class="font-semibold">Enforcement is currently OFF</p>
                    <p class="mt-1 text-xs">
                        Credit-hold checks in the pig sale form are disabled by the config flag
                        <code class="px-1 bg-blue-100 rounded">finance.credit_control.enforce_in_pig_sales</code>.
                        Settings on this page will be saved and visible in the AR Ageing report, but the pig sale form
                        won't act on them until the flag is turned on.
                    </p>
                </div>
            @endif

            <div class="flex gap-3 justify-end mt-4">
                <a href="{{ route('finance.customers.show', $customer) }}" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Save Credit Settings</button>
            </div>
        </form>
    </div>
</x-app-layout>
