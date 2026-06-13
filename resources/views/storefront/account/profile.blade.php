<x-storefront.layout :title="'Profile'">
    <div class="max-w-2xl mx-auto px-4 py-10">
        <a href="{{ route('storefront.account.dashboard') }}" class="text-sm text-slate-500">← Account</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-2 mb-6">Profile</h1>
        <div class="rounded-2xl bg-white border border-slate-200 p-6">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <dt class="text-slate-400">Name</dt><dd>{{ $user->name }}</dd>
                <dt class="text-slate-400">Email</dt><dd>{{ $user->email }}</dd>
                <dt class="text-slate-400">Customer code</dt><dd class="font-mono">{{ $counterparty?->code }}</dd>
                <dt class="text-slate-400">Currency</dt><dd>{{ $counterparty?->currency_code ?? 'GHS' }}</dd>
            </dl>
            <p class="text-xs text-slate-400 mt-4">To change your name or email, use your account settings.</p>
        </div>
    </div>
</x-storefront.layout>
