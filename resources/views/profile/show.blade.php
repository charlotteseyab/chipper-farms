<x-app-layout>
    @php
        $user = auth()->user();
        $role = $user->isSuperuser() ? 'superuser' : ($user->isAdmin() ? 'admin' : ($user->isManager() ? 'farmmanager' : ($user->isAccountant() ? 'accountant' : ($user->isTreasurer() ? 'treasurer' : ($user->isSalesperson() ? 'salesperson' : ($user->isDataEntry() ? 'dataentry' : ($user->isCustomer() ? 'customer' : 'user')))))));
        $roles = [
            'superuser' => ['System Superuser', 'Platform governance and system-wide oversight', 'Platform account', 'System settings, organizations, platform users and consolidated reports'],
            'admin' => ['Organization Administrator', 'Full administration within your organization', 'Organization account', 'Users, setup, operations, finance and organization reporting'],
            'farmmanager' => ['Farm Manager', 'Day-to-day farm operations and team oversight', 'Organization account', 'Livestock, breeding, health, feed and operational reporting'],
            'accountant' => ['Accountant', 'Financial recording, controls and reporting', 'Organization account', 'Finance, sales, purchases, reconciliation and reports'],
            'treasurer' => ['Treasurer', 'Cash, banking and payment oversight', 'Organization account', 'Money accounts, payments, receipts and treasury workflows'],
            'salesperson' => ['Salesperson', 'Sales and storefront order operations', 'Organization account', 'Customers, sales, orders and permitted storefront workflows'],
            'dataentry' => ['Data Entry Officer', 'Accurate recording of farm activity', 'Organization account', 'Assigned livestock and operational data-entry workflows'],
            'customer' => ['Buyer', 'Storefront purchases and account information', 'Buyer account', 'Orders, quotes, addresses and buyer notifications'],
            'user' => ['System User', 'Access is based on permissions assigned to this account', 'Organization account', 'Only modules granted by an administrator'],
        ];
        [$roleLabel, $rolePurpose, $scopeLabel, $accessSummary] = $roles[$role];
        $dashboardRoute = match ($role) {
            'superuser' => 'superuser.dashboard',
            'admin' => 'admin.dashboard',
            'farmmanager' => 'manager.dashboard',
            'dataentry' => 'dataentry.dashboard',
            'customer' => Route::has('storefront.account.dashboard') ? 'storefront.account.dashboard' : 'dashboard',
            default => 'dashboard',
        };
        $initials = collect([$user->first_name, $user->last_name])->filter()->map(fn ($part) => mb_substr($part, 0, 1))->join('') ?: mb_substr($user->name ?: $user->email, 0, 2);
    @endphp

    <main class="min-h-screen bg-slate-50/70 pb-28">
        <div class="mx-auto max-w-7xl space-y-6 px-3 py-4 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-3xl border border-emerald-900/10 shadow-xl" style="background:linear-gradient(135deg,#0f172a 0%,#16352f 58%,#047857 100%);color:#fff">
                <div class="flex flex-col gap-6 p-6 sm:p-8 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 items-center gap-4 sm:gap-5">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl border border-white/20 bg-white/10 text-xl font-black uppercase sm:h-20 sm:w-20 sm:text-2xl">{{ $initials }}</div>
                        <div class="min-w-0"><p class="text-xs font-black uppercase tracking-[.18em]" style="color:#6ee7b7">{{ $scopeLabel }}</p><h1 class="mt-1 truncate text-2xl font-black sm:text-3xl" style="color:#fff">{{ $user->first_name || $user->last_name ? trim($user->first_name.' '.$user->last_name) : $user->name }}</h1><p class="mt-1 truncate text-sm" style="color:#dbeafe">{{ $user->email }}</p></div>
                    </div>
                    <a href="{{ route($dashboardRoute) }}" class="inline-flex items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-black text-slate-900 shadow-sm">Return to dashboard</a>
                </div>
                <div class="grid border-t border-white/10 sm:grid-cols-3">
                    <div class="p-5"><p class="text-[11px] font-black uppercase tracking-wider" style="color:#a7f3d0">Role</p><p class="mt-1 font-bold" style="color:#fff">{{ $roleLabel }}</p></div>
                    <div class="border-white/10 p-5 sm:border-l"><p class="text-[11px] font-black uppercase tracking-wider" style="color:#a7f3d0">Organization</p><p class="mt-1 font-bold" style="color:#fff">{{ $user->isSuperuser() ? 'Not assigned — platform-wide' : ($user->organization?->name ?? 'Not assigned') }}</p></div>
                    <div class="border-white/10 p-5 sm:border-l"><p class="text-[11px] font-black uppercase tracking-wider" style="color:#a7f3d0">Account status</p><p class="mt-1 font-bold" style="color:#fff">{{ $user->active_status ? 'Active' : 'Inactive' }} · {{ $user->hasVerifiedEmail() ? 'Email verified' : 'Email not verified' }}</p></div>
                </div>
            </section>

            <section class="grid gap-4 lg:grid-cols-3">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700"><x-ui-icon name="user" class="h-5 w-5" /></div><h2 class="mt-4 font-black text-slate-950">Your responsibility</h2><p class="mt-2 text-sm leading-6 text-slate-600">{{ $rolePurpose }}.</p></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 text-blue-700"><x-ui-icon name="shield" class="h-5 w-5" /></div><h2 class="mt-4 font-black text-slate-950">Access boundary</h2><p class="mt-2 text-sm leading-6 text-slate-600">{{ $accessSummary }}.</p></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-700"><x-ui-icon name="lock" class="h-5 w-5" /></div><h2 class="mt-4 font-black text-slate-950">Managed access</h2><p class="mt-2 text-sm leading-6 text-slate-600">{{ $user->isSuperuser() ? 'Platform scope and role are controlled by another system superuser.' : 'Your organization, role and module permissions are controlled by your organization administrator.' }}</p></article>
            </section>

            <div class="rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm leading-6 text-blue-950"><strong>You can update:</strong> your name, phone, email, profile photo, password and personal security. <strong>You cannot update:</strong> your role, organization or assigned permissions from this page.</div>

            <section class="space-y-6">
                @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:p-8">@livewire('profile.update-profile-information-form')</div>
                @endif

                @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:p-8">@livewire('profile.update-password-form')</div>
                @endif

                @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:p-8">@livewire('profile.two-factor-authentication-form')</div>
                @endif

                <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:p-8">@livewire('profile.logout-other-browser-sessions-form')</div>

                @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures() && $user->isSuperuser())
                    <div class="overflow-hidden rounded-3xl border border-red-200 bg-white p-5 shadow-sm sm:p-6 lg:p-8">@livewire('profile.delete-user-form')</div>
                @endif
            </section>
        </div>
    </main>
</x-app-layout>
