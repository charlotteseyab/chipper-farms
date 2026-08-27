@php
    $unreadStoredCount = $user ? $user->unreadNotifications()->count() : 0;
    $recentNotifications = $user ? $user->notifications()->latest()->limit(7)->get() : collect();

    try {
        $realTimeAlerts = \App\Services\RealTimeNotificationService::getAllAlerts();
        $allAlertItems = collect();
        $allowedGroups = collect([
            'weight' => ['view_weights'],
            'gestation' => ['view_gestation'],
            'ingredient' => ['view_raw_materials', 'view_inventory_value'],
        ])->filter(fn ($permissions) => $user && ($user->isSuperuser() || $user->isAdmin() || collect($permissions)->contains(fn ($permission) => $user->hasPermission($permission))))->keys();

        foreach ($realTimeAlerts as $group => $data) {
            if (! $allowedGroups->contains($group)) continue;
            foreach (($data['alerts'] ?? []) as $alert) {
                $allAlertItems->push($alert);
            }
        }

        $allAlertItems = $allAlertItems->sortByDesc(fn ($alert) => $alert['severity'] === 'high' ? 2 : ($alert['severity'] === 'medium' ? 1 : 0));
        $realTimeAlertCount = $allAlertItems->sum(fn ($alert) => (int) ($alert['count'] ?? 1));
    } catch (\Throwable $e) {
        $realTimeAlertCount = 0;
        $allAlertItems = collect();
    }

    $totalNotificationCount = $unreadStoredCount + $realTimeAlertCount;
@endphp

<style>
    #notificationDropdownPanel{
        z-index:60;
    }

    #notificationDropdownPanel:not(.hidden){
        display:flex;
        flex-direction:column;
    }

    .notification-dropdown-scroll{
        min-height:0;
    }

    @media (max-width: 640px){
        #notificationDropdownPanel{
            position:fixed !important;
            top:6.75rem !important;
            right:12px !important;
            left:12px !important;
            width:auto !important;
            max-width:none !important;
            max-height:calc(100dvh - 7.5rem);
            margin-top:0 !important;
            border-radius:20px;
        }

        .notification-dropdown-head{
            align-items:flex-start !important;
            gap:12px;
        }

        .notification-dropdown-head > div{
            min-width:0;
        }

        .notification-dropdown-head p{
            overflow-wrap:anywhere;
        }

        .notification-dropdown-scroll{
            flex:1 1 auto;
            max-height:none !important;
            overflow-y:auto;
            overscroll-behavior:contain;
        }
    }

    @media (max-width: 380px){
        #notificationDropdownPanel{
            right:8px !important;
            left:8px !important;
        }

        .notification-dropdown-head{
            flex-direction:column;
        }
    }
</style>

<div id="notificationDropdown" data-open="false" class="relative">
    <button
        id="notificationDropdownToggle"
        type="button"
        aria-haspopup="true"
        aria-expanded="false"
        class="flex items-center justify-center rounded-2xl border border-slate-200 bg-white p-2.5 shadow-sm transition hover:border-green-200 hover:bg-slate-50"
    >
        <span class="relative">
            <i class="fa-regular fa-bell text-slate-600"></i>
            @if($totalNotificationCount > 0)
                <span class="absolute -right-2 -top-2 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[11px] font-semibold text-white">{{ $totalNotificationCount }}</span>
            @endif
        </span>
    </button>

    <div id="notificationDropdownPanel" class="absolute right-0 mt-3 hidden w-[22rem] overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl">
        <div class="notification-dropdown-head flex items-center justify-between border-b border-slate-100 bg-slate-50 px-4 py-3">
            <div>
                <p class="text-sm font-semibold text-slate-900">Notifications</p>
                <p class="text-xs text-slate-500">Stored alerts and real-time farm reminders.</p>
            </div>
            @if($unreadStoredCount > 0)
                <form action="{{ route('notifications.readAll') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-xs font-semibold text-green-700 transition hover:text-green-800">Mark all read</button>
                </form>
            @endif
        </div>

        <div class="notification-dropdown-scroll max-h-96 overflow-y-auto">
            @if($allAlertItems->isNotEmpty())
                <div class="border-b border-slate-100 px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Real-time alerts</p>
                </div>
                @foreach($allAlertItems->take(5) as $alert)
                    <div class="border-b border-slate-100 px-4 py-3 last:border-b-0">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                                <x-ui-icon :name="$alert['icon'] ?? 'notification'" class="h-4 w-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm font-semibold text-slate-900">{{ $alert['title'] }}</p>
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ ($alert['severity'] ?? null) === 'high' ? 'bg-red-100 text-red-700' : (($alert['severity'] ?? null) === 'medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-blue-100 text-blue-700') }}">{{ $alert['count'] ?? 1 }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-600">{{ $alert['message'] }}</p>
                                @if(!empty($alert['link']))
                                    <a href="{{ $alert['link'] }}" class="mt-2 inline-flex items-center text-xs font-semibold text-green-700 hover:text-green-800">
                                        View details
                                        <i class="fa-solid fa-arrow-right ml-1 text-[10px]"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif

            <div class="{{ $allAlertItems->isNotEmpty() ? 'border-t border-slate-100' : '' }} px-4 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Recent stored notifications</p>
            </div>

            @forelse($recentNotifications as $notification)
                @php($data = $notification->data)
                <a href="{{ $data['link'] ?? route('notifications.index') }}" class="flex items-start gap-3 border-t border-slate-100 px-4 py-3 transition hover:bg-slate-50 {{ is_null($notification->read_at) ? 'bg-blue-50/60' : '' }}">
                    <span class="mt-1 inline-flex h-2.5 w-2.5 rounded-full {{ is_null($notification->read_at) ? 'bg-blue-600' : 'bg-slate-300' }}"></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-800">{{ ucfirst(str_replace('_', ' ', $data['type'] ?? 'notification')) }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                </a>
            @empty
                @if($allAlertItems->isEmpty())
                    <div class="px-4 py-8 text-center text-sm text-slate-500">No notifications at the moment.</div>
                @endif
            @endforelse
        </div>

        <div class="border-t border-slate-100 bg-slate-50 px-4 py-3 text-center">
            <a href="{{ route('notifications.index') }}" class="text-xs font-semibold text-green-700 hover:text-green-800">Open full notification centre</a>
        </div>
    </div>
</div>
