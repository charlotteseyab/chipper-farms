<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @php $dashboardAccess = \App\Services\Access\DashboardAccess::for(auth()->user()); @endphp

            @if($dashboardAccess->sees('weights'))
                <x-weight-analytics-summary />
            @endif

            {{-- Each panel hides itself unless the user may open its module. --}}
            <x-dashboard.livestock-overview />

            <x-dashboard.health-overview />

            @unless($dashboardAccess->sees('weights') || $dashboardAccess->sees('pigs') || $dashboardAccess->sees('health'))
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6 text-center">
                    <h2 class="text-lg font-semibold text-gray-900">Nothing is assigned to your role yet</h2>
                    <p class="mt-1 text-sm text-gray-600">Ask your farm admin to give your role access in Settings → Roles &amp; access.</p>
                </div>
            @endunless
            
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <x-welcome />
            </div>
        </div>
    </div>
</x-app-layout>
