@php $money = fn ($v) => number_format((float) $v, 2); @endphp

<x-storefront.layout :title="'Create buyer account for ' . $order->reference">
    <div class="max-w-3xl mx-auto px-4 py-10">
        <div class="rounded-2xl border border-slate-200 bg-white p-6">
            <p class="text-sm font-semibold text-emerald-700">Buyer account</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">Save order {{ $order->reference }}</h1>
            <p class="mt-2 text-sm text-slate-500">
                Your checkout details are already filled in. Choose a password and this order will move into your buyer account.
            </p>

            <div class="mt-5 rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm">
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Order total</span>
                    <span class="font-semibold text-slate-800">GHS {{ $money($order->total) }}</span>
                </div>
                <div class="mt-1 flex justify-between gap-4">
                    <span class="text-slate-500">Status</span>
                    <span class="font-semibold text-slate-800">{{ str_replace('_', ' ', $order->status) }}</span>
                </div>
            </div>

            @if($errors->any())
                <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('storefront.buyer.register.from-order.store', ['reference' => $order->reference, 'token' => $token]) }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label class="text-sm font-medium text-slate-700" for="name">Name</label>
                    <input id="name" name="name" value="{{ old('name', $order->guest_name ?: $order->ship_to_name) }}" required maxlength="200" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700" for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $order->guest_email) }}" required readonly maxlength="190" class="mt-1 w-full rounded-xl border-slate-300 bg-slate-50 text-sm text-slate-600">
                    <p class="mt-1 text-xs text-slate-500">This must match the address that received the secure order link.</p>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700" for="phone">Phone</label>
                    <input id="phone" name="phone" value="{{ old('phone', $order->guest_phone ?: $order->ship_to_phone) }}" maxlength="60" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-700" for="password">Password</label>
                        <input id="password" name="password" type="password" required minlength="8" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700" for="password_confirmation">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    </div>
                </div>

                <div class="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center">
                    <button type="submit" class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white">
                        Create buyer account
                    </button>
                    <a href="{{ route('storefront.buyer.login', ['email' => $order->guest_email, 'claim_reference' => $order->reference, 'claim_token' => $token]) }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                        I already have a buyer account
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-storefront.layout>
