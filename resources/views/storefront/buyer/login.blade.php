<x-storefront.layout title="Buyer sign in">
    <div class="max-w-md mx-auto px-4 py-12">
        <div class="rounded-2xl border border-slate-200 bg-white p-6">
            <p class="text-sm font-semibold text-emerald-700">Buyer account</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">Sign in to your orders</h1>

            @if($errors->any())
                <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('storefront.buyer.login.store') }}" class="mt-6 space-y-4">
                @csrf
                @if($claimReference && $claimToken)
                    <input type="hidden" name="claim_reference" value="{{ $claimReference }}">
                    <input type="hidden" name="claim_token" value="{{ $claimToken }}">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">Sign in with the order email and we’ll save the guest order to your account.</div>
                @endif
                <div>
                    <label class="text-sm font-medium text-slate-700" for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', request('email')) }}" required class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700" for="password">Password</label>
                    <input id="password" name="password" type="password" required class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                </div>
                <button type="submit" class="w-full rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white">
                    Sign in
                </button>
            </form>
        </div>
    </div>
</x-storefront.layout>
