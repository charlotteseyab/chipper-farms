<nav x-data="{ open: false }" class="bg-white border-b border-gray-100 fixed top-0 left-72 w-[calc(100%-18rem)] z-40 rounded-bl-2xl shadow-sm">
    <!-- Primary Navigation Menu -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                {{-- <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-mark class="block h-9 w-auto" />
                    </a>
                </div> --}}

                <!-- Navigation Links -->
                {{-- <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                </div> --}}
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <!-- Notifications Bell with Real-Time Alerts -->
                @auth
                @php
                    $user = auth()->user();
                    $isSuperuser = $user && optional($user->role)->name === 'superuser';
                    
                    // Get stored notifications
                    if ($isSuperuser) {
                        $unreadStoredCount = \Illuminate\Notifications\DatabaseNotification::whereNull('read_at')->count();
                    $recentNotifications = \Illuminate\Notifications\DatabaseNotification::latest()->limit(7)->get();
                    } else {
                        $unreadStoredCount = $user ? $user->unreadNotifications()->count() : 0;
                        $recentNotifications = $user ? $user->notifications()->latest()->limit(7)->get() : collect();
                    }
                    
                    // Get real-time alerts
                    try {
                        $realTimeAlerts = \App\Services\RealTimeNotificationService::getAllAlerts();
                        $realTimeAlertCount = \App\Services\RealTimeNotificationService::getTotalAlertCount();
                        
                        // Combine all alerts into a single list
                        $allAlertItems = collect();
                        foreach ($realTimeAlerts as $category => $data) {
                            if (isset($data['alerts']) && is_array($data['alerts'])) {
                                foreach ($data['alerts'] as $alert) {
                                    $allAlertItems->push($alert);
                                }
                            }
                        }
                        
                        // Sort by severity (high priority first)
                        $allAlertItems = $allAlertItems->sortByDesc(function($alert) {
                            return $alert['severity'] === 'high' ? 2 : ($alert['severity'] === 'medium' ? 1 : 0);
                        });
                    } catch (\Exception $e) {
                        $realTimeAlertCount = 0;
                        $allAlertItems = collect();
                    }
                    
                    // Total notification count (stored + real-time alerts)
                    $totalNotificationCount = $unreadStoredCount + $realTimeAlertCount;
                @endphp
                <div class="mr-4 relative" x-data="{ openBell: false }" @click.outside="openBell = false">
                    <button type="button" @click="openBell = !openBell" class="relative inline-flex items-center text-gray-500 hover:text-gray-700 focus:outline-none">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        @if($totalNotificationCount > 0)
                            <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-red-600 rounded-full">{{ $totalNotificationCount }}</span>
                        @endif
                    </button>

                    <!-- Dropdown Panel -->
                    <div x-show="openBell" x-transition class="absolute right-0 mt-2 w-96 bg-white rounded-lg shadow-lg border border-gray-200 z-50 max-h-96 overflow-y-auto">
                        <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-800">Notifications & Alerts</span>
                            @if($unreadStoredCount > 0)
                                <form action="{{ route('notifications.readAll') }}" method="POST">
                                    @csrf
                                    <button class="text-xs text-gray-500 hover:text-gray-700">Mark all as read</button>
                                </form>
                            @endif
                        </div>
                        
                        <div class="divide-y divide-gray-100">
                            <!-- Real-Time Alerts Section -->
                            @if($realTimeAlertCount > 0)
                                <!-- <div class="px-4 py-2 bg-red-50 border-b border-red-100">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-semibold text-red-800 uppercase tracking-wide">Real-Time Alerts</span>
                                        <span class="text-xs text-red-600 font-medium">{{ $realTimeAlertCount }} active</span>
                                    </div>
                                </div> -->
                                
                                @foreach($allAlertItems->take(5) as $alert)
                                    <div class="px-4 py-3 hover:bg-gray-50 transition border-l-4 
                                        @if($alert['severity'] === 'high') border-red-500 bg-red-50
                                        @elseif($alert['severity'] === 'medium') border-yellow-500 bg-yellow-50
                                        @else border-blue-500 bg-blue-50
                                        @endif">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 mt-0.5">
                                                <span class="text-xl">{{ $alert['icon'] }}</span>
                                            </div>
                                            <div class="ml-3 flex-1">
                                                <div class="flex items-center justify-between">
                                                    <p class="text-sm font-medium text-gray-900">
                                                        {{ $alert['title'] }}
                                                    </p>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                        @if($alert['severity'] === 'high') bg-red-100 text-red-800
                                                        @elseif($alert['severity'] === 'medium') bg-yellow-100 text-yellow-800
                                                        @else bg-blue-100 text-blue-800
                                                        @endif">
                                                        {{ $alert['count'] }}
                                                    </span>
                                                </div>
                                                <p class="text-xs text-gray-600 mt-1">{{ $alert['message'] }}</p>
                                                
                                                @if(!empty($alert['items']) && count($alert['items']) > 0)
                                                    <div class="mt-2 space-y-1">
                                                        @foreach(array_slice($alert['items'], 0, 2) as $item)
                                                            <div class="text-xs text-gray-500">
                                                                @if(isset($item['ear_tag']))
                                                                    • {{ $item['ear_tag'] }}
                                                                    @if(isset($item['days_since']))
                                                                        ({{ $item['days_since'] }} days)
                                                                    @endif
                                                                @elseif(isset($item['sow_ear_tag']))
                                                                    • Sow {{ $item['sow_ear_tag'] }}
                                                                    @if(isset($item['days_overdue']))
                                                                        ({{ $item['days_overdue'] }} days overdue)
                                                                    @endif
                                                                @elseif(isset($item['name']))
                                                                    • {{ $item['name'] }} ({{ $item['quantity'] }} {{ $item['unit'] }})
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                        @if(count($alert['items']) > 2)
                                                            <div class="text-xs text-gray-400">
                                                                + {{ count($alert['items']) - 2 }} more...
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif
                                                
                                                <div class="mt-2">
                                                    @if(in_array($alert['type'] ?? '', ['weight_overdue', 'weight_critical']))
                                                        <a href="{{ route('admin.dashboard') }}#weighing-alerts-card" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                                                            View on Dashboard →
                                                        </a>
                                                    @else
                                                        <a href="{{ $alert['link'] }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                                                            View details →
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                                
                                @if($allAlertItems->count() > 5)
                                    <div class="px-4 py-2 text-center bg-gray-50">
                                        <span class="text-xs text-gray-500">
                                            + {{ $allAlertItems->count() - 5 }} more alert(s)
                                        </span>
                                    </div>
                                @endif
                                
                                @if($unreadStoredCount > 0)
                                    <div class="px-4 py-2 bg-gray-50 border-t border-gray-200">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Stored Notifications</span>
                                            <span class="text-xs text-gray-600">{{ $unreadStoredCount }} unread</span>
                                        </div>
                                    </div>
                                @endif
                            @endif
                            
                            <!-- Stored Notifications Section -->
                            <ul class="divide-y divide-gray-100">
                            @forelse($recentNotifications as $notification)
                                @php $data = $notification->data; @endphp
                                    <li class="px-4 py-3 {{ is_null($notification->read_at) ? 'bg-blue-50' : 'bg-white' }} hover:bg-gray-50 transition">
                                    <a href="{{ $data['link'] ?? route('notifications.index') }}" class="block">
                                            <div class="flex items-start">
                                                <div class="flex-1">
                                        <div class="text-sm text-gray-700">
                                            <span class="font-medium">{{ ucfirst(str_replace('_',' ', $data['type'] ?? 'notification')) }}</span>
                                            @if(isset($data['ear_tag']))
                                                • Pig {{ $data['ear_tag'] }}
                                            @elseif(isset($data['item_name']))
                                                • {{ $data['item_name'] }} ({{ $data['quantity'] ?? '' }})
                                            @elseif(isset($data['feed_name']))
                                                • {{ $data['feed_name'] }} ({{ $data['current_stock'] ?? '' }})
                                                        @elseif(isset($data['sow_id']))
                                                            • Sow {{ $data['ear_tag'] ?? 'N/A' }}
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-500 mt-0.5">
                                            {{ $notification->created_at->diffForHumans() }}
                                                    </div>
                                                </div>
                                                @if(is_null($notification->read_at))
                                                    <div class="ml-2 flex-shrink-0">
                                                        <span class="inline-flex items-center justify-center w-2 h-2 bg-blue-600 rounded-full"></span>
                                                    </div>
                                                @endif
                                        </div>
                                    </a>
                                </li>
                            @empty
                                    @if($realTimeAlertCount == 0)
                                        <li class="px-4 py-6 text-center text-sm text-gray-500">
                                            No notifications
                                        </li>
                                    @endif
                            @endforelse
                        </ul>
                        </div>
                        
                        <div class="px-4 py-3 border-t border-gray-100 bg-gray-50 text-center">
                            <a href="{{ route('notifications.index') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">View all notifications</a>
                        </div>
                    </div>
                </div>
                @endauth
                <!-- Teams Dropdown -->
                @if (Laravel\Jetstream\Jetstream::hasTeamFeatures())
                    <div class="ms-3 relative">
                        <x-dropdown align="right" width="60">
                            <x-slot name="trigger">
                                <span class="inline-flex rounded-md">
                                    <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                        {{ Auth::user()->currentTeam->name }}

                                        <svg class="ms-2 -me-0.5 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                                        </svg>
                                    </button>
                                </span>
                            </x-slot>

                            <x-slot name="content">
                                <div class="w-60">
                                    <!-- Team Management -->
                                    <div class="block px-4 py-2 text-xs text-gray-400">
                                        {{ __('Manage Team') }}
                                    </div>

                                    <!-- Team Settings -->
                                    <x-dropdown-link href="{{ route('teams.show', Auth::user()->currentTeam->id) }}">
                                        {{ __('Team Settings') }}
                                    </x-dropdown-link>

                                    @can('create', Laravel\Jetstream\Jetstream::newTeamModel())
                                        <x-dropdown-link href="{{ route('teams.create') }}">
                                            {{ __('Create New Team') }}
                                        </x-dropdown-link>
                                    @endcan

                                    <!-- Team Switcher -->
                                    @if (Auth::user()->allTeams()->count() > 1)
                                        <div class="border-t border-gray-200"></div>

                                        <div class="block px-4 py-2 text-xs text-gray-400">
                                            {{ __('Switch Teams') }}
                                        </div>

                                        @foreach (Auth::user()->allTeams() as $team)
                                            <x-switchable-team :team="$team" />
                                        @endforeach
                                    @endif
                                </div>
                            </x-slot>
                        </x-dropdown>
                    </div>
                @endif

                <!-- Settings Dropdown -->
                <div class="ms-3 relative">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                                <button class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                                    <img class="size-8 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                                </button>
                            @else
                                <span class="inline-flex rounded-md">
                                    <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                        {{ Auth::user()->name }}

                                        <svg class="ms-2 -me-0.5 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                </span>
                            @endif
                        </x-slot>

                        <x-slot name="content">
                            <!-- Account Management -->
                            <div class="block px-4 py-2 text-xs text-gray-400">
                                {{ __('Manage Account') }}
                            </div>

                            <x-dropdown-link href="{{ route('profile.show') }}">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                                <x-dropdown-link href="{{ route('api-tokens.index') }}">
                                    {{ __('API Tokens') }}
                                </x-dropdown-link>
                            @endif

                            <div class="border-t border-gray-200"></div>

                            <!-- Authentication -->
                            <form method="POST" action="{{ route('logout') }}" x-data>
                                @csrf

                                <x-dropdown-link href="{{ route('logout') }}"
                                         @click.prevent="$root.submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="size-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="flex items-center px-4">
                @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                    <div class="shrink-0 me-3">
                        <img class="size-10 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                    </div>
                @endif

                <div>
                    <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                </div>
            </div>

            <div class="mt-3 space-y-1">
                <!-- Account Management -->
                <x-responsive-nav-link href="{{ route('profile.show') }}" :active="request()->routeIs('profile.show')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                    <x-responsive-nav-link href="{{ route('api-tokens.index') }}" :active="request()->routeIs('api-tokens.index')">
                        {{ __('API Tokens') }}
                    </x-responsive-nav-link>
                @endif

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}" x-data>
                    @csrf

                    <x-responsive-nav-link href="{{ route('logout') }}"
                                   @click.prevent="$root.submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>

                <!-- Team Management -->
                @if (Laravel\Jetstream\Jetstream::hasTeamFeatures())
                    <div class="border-t border-gray-200"></div>

                    <div class="block px-4 py-2 text-xs text-gray-400">
                        {{ __('Manage Team') }}
                    </div>

                    <!-- Team Settings -->
                    <x-responsive-nav-link href="{{ route('teams.show', Auth::user()->currentTeam->id) }}" :active="request()->routeIs('teams.show')">
                        {{ __('Team Settings') }}
                    </x-responsive-nav-link>

                    @can('create', Laravel\Jetstream\Jetstream::newTeamModel())
                        <x-responsive-nav-link href="{{ route('teams.create') }}" :active="request()->routeIs('teams.create')">
                            {{ __('Create New Team') }}
                        </x-responsive-nav-link>
                    @endcan

                    <!-- Team Switcher -->
                    @if (Auth::user()->allTeams()->count() > 1)
                        <div class="border-t border-gray-200"></div>

                        <div class="block px-4 py-2 text-xs text-gray-400">
                            {{ __('Switch Teams') }}
                        </div>

                        @foreach (Auth::user()->allTeams() as $team)
                            <x-switchable-team :team="$team" component="responsive-nav-link" />
                        @endforeach
                    @endif
                @endif
            </div>
        </div>
    </div>
</nav>
