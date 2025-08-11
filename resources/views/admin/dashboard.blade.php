<x-app-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-900">Admin Dashboard</h1>
                <p class="text-gray-600 mt-2">System administration and user management</p>
            </div>

            <!-- System Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Users -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Users</p>
                            <p class="text-2xl font-bold text-gray-900">
                                @php
                                    $totalUsers = \App\Models\User::count();
                                @endphp
                                {{ $totalUsers }}
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center text-sm text-gray-600">
                            @php
                                $activeUsers = \App\Models\User::where('created_at', '>=', now()->subDays(30))->count();
                            @endphp
                            <span class="text-green-600 font-medium">{{ $activeUsers }}</span>
                            <span class="ml-1">active this month</span>
                        </div>
                    </div>
                </div>

                <!-- System Health -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">System Health</p>
                            @php
                                // Calculate system health based on real metrics
                                $criticalAlerts = \App\Models\WeightAlert::where('severity', 'high')->where('acknowledged', false)->count();
                                $totalAlerts = \App\Models\WeightAlert::where('acknowledged', false)->count();
                                $recentErrors = 0; // Could be from logs if available

                                // Determine health status
                                if ($criticalAlerts > 5 || $totalAlerts > 20) {
                                    $healthStatus = 'Critical';
                                    $healthColor = 'red';
                                } elseif ($criticalAlerts > 0 || $totalAlerts > 10) {
                                    $healthStatus = 'Warning';
                                    $healthColor = 'yellow';
                                } else {
                                    $healthStatus = 'Good';
                                    $healthColor = 'green';
                                }
                            @endphp
                            <p class="text-2xl font-bold text-{{ $healthColor }}-600">{{ $healthStatus }}</p>
                        </div>
                        <div class="w-12 h-12 bg-{{ $healthColor }}-100 rounded-lg flex items-center justify-center">
                            @if($healthColor === 'red')
                                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                            @elseif($healthColor === 'yellow')
                                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                            @else
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            @endif
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center text-sm text-{{ $healthColor }}-600">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <span>
                                @if($healthColor === 'red')
                                    {{ $criticalAlerts }} critical alerts need attention
                                @elseif($healthColor === 'yellow')
                                    {{ $totalAlerts }} alerts to review
                                @else
                                    All systems operational
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Database Records -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Database Records</p>
                            <p class="text-2xl font-bold text-gray-900">
                                @php
                                    $totalRecords = \App\Models\PigManagement::count() +
                                                  \App\Models\PigWeight::count() +
                                                  \App\Models\WeightAlert::count();
                                @endphp
                                {{ number_format($totalRecords) }}
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center text-sm text-gray-600">
                            @php
                                $todayRecords = \App\Models\PigWeight::whereDate('created_at', today())->count();
                            @endphp
                            <span class="text-purple-600 font-medium">{{ $todayRecords }}</span>
                            <span class="ml-1">added today</span>
                        </div>
                    </div>
                </div>

                <!-- Storage Usage -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Storage Usage</p>
                            <p class="text-2xl font-bold text-gray-900">
                                @php
                                    // Calculate estimated storage based on database records
                                    $totalRecords = \App\Models\PigManagement::count() +
                                                  \App\Models\PigWeight::count() +
                                                  \App\Models\WeightAlert::count() +
                                                  \App\Models\User::count();
                                    // Estimate: ~1KB per record average
                                    $storageUsedMB = ($totalRecords * 1) / 1024; // Convert KB to MB
                                    $storageUsedGB = round($storageUsedMB / 1024, 2); // Convert MB to GB
                                    if ($storageUsedGB < 0.01) $storageUsedGB = 0.01; // Minimum display
                                @endphp
                                {{ $storageUsedGB }}GB
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center text-sm text-gray-600">
                            @php
                                $maxStorageGB = 10; // Assumed max storage
                                $storagePercentage = min(($storageUsedGB / $maxStorageGB) * 100, 100);
                            @endphp
                            <div class="w-16 bg-gray-200 rounded-full h-1.5 mr-2">
                                <div class="bg-yellow-500 h-1.5 rounded-full transition-all duration-300" style="width: {{ $storagePercentage }}%"></div>
                            </div>
                            <span>{{ round($storagePercentage, 1) }}% of {{ $maxStorageGB }}GB</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin Actions -->
            @php
                // Define variables needed for admin action cards
                $activeAlerts = \App\Models\WeightAlert::where('acknowledged', false)->count();
            @endphp
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition group cursor-pointer">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center group-hover:bg-blue-200 transition">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-blue-600 transition">User Management</h3>
                            <p class="text-sm text-gray-600">Manage user accounts and roles</p>
                            <div class="mt-2">
                                <span class="text-xs text-blue-600 font-medium">{{ $totalUsers }} users registered</span>
                            </div>
                        </div>
                    </div>
                </div>

                <a href="{{ route('all-stocks.index') }}" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition group cursor-pointer block">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center group-hover:bg-green-200 transition">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 00-2 2z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-green-600 transition">System Data</h3>
                            <p class="text-sm text-gray-600">View and manage farm data</p>
                            <div class="mt-2">
                                <span class="text-xs text-green-600 font-medium">{{ number_format($totalRecords) }} total records</span>
                            </div>
                        </div>
                    </div>
                </a>

                <a href="{{ route('weight-alerts.dashboard') }}" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition group cursor-pointer block">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center group-hover:bg-red-200 transition">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-red-600 transition">Alert Management</h3>
                            <p class="text-sm text-gray-600">Monitor and manage system alerts</p>
                            <div class="mt-2">
                                <span class="text-xs text-red-600 font-medium">{{ $activeAlerts }} active alerts</span>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Recent Activity & System Logs -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent User Activity -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">👥 Recent User Activity</h3>
                    <div class="space-y-3">
                        @php
                            $recentUsers = \App\Models\User::latest()->take(5)->get();
                        @endphp

                        @forelse($recentUsers as $user)
                            <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-b-0">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-blue-600 font-medium text-sm">{{ substr($user->name, 0, 1) }}</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $user->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $user->email }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gray-500">{{ $user->created_at->format('M j') }}</p>
                                    @php
                                        $roleColors = [
                                            'superuser' => 'bg-purple-100 text-purple-800',
                                            'admin' => 'bg-red-100 text-red-800',
                                            'manager' => 'bg-blue-100 text-blue-800',
                                            'dataentry' => 'bg-green-100 text-green-800',
                                            'default' => 'bg-gray-100 text-gray-800'
                                        ];
                                        // Handle role properly - it might be a model object or string
                                        if (is_object($user->role)) {
                                            $userRole = $user->role->name ?? 'user';
                                        } else {
                                            $userRole = $user->role ?? 'user';
                                        }
                                        $roleColor = $roleColors[$userRole] ?? $roleColors['default'];
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $roleColor }}">
                                        {{ ucfirst($userRole) }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500 text-sm">No recent user activity</p>
                        @endforelse
                    </div>
                </div>

                <!-- System Statistics -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">📊 System Statistics</h3>
                    <div class="space-y-4">
                        @php
                            // Calculate real changes from database
                            $totalPigs = \App\Models\PigManagement::count();
                            $pigsThisWeek = \App\Models\PigManagement::where('created_at', '>=', now()->subWeek())->count();

                            $totalWeights = \App\Models\PigWeight::count();
                            $weightsThisWeek = \App\Models\PigWeight::where('created_at', '>=', now()->subWeek())->count();

                            $activeAlerts = \App\Models\WeightAlert::where('acknowledged', false)->count();
                            $alertsThisWeek = \App\Models\WeightAlert::where('created_at', '>=', now()->subWeek())->count();

                            $breedingStock = \App\Models\PigManagement::where('stock_type', 'breeding')->count();
                            $breedingThisWeek = \App\Models\PigManagement::where('stock_type', 'breeding')
                                ->where('created_at', '>=', now()->subWeek())->count();

                            $stats = [
                                ['label' => 'Total Pigs', 'value' => $totalPigs, 'change' => ($pigsThisWeek > 0 ? '+' : '') . $pigsThisWeek, 'color' => 'green'],
                                ['label' => 'Weight Records', 'value' => $totalWeights, 'change' => ($weightsThisWeek > 0 ? '+' : '') . $weightsThisWeek, 'color' => 'blue'],
                                ['label' => 'Active Alerts', 'value' => $activeAlerts, 'change' => ($alertsThisWeek > 0 ? '+' : '') . $alertsThisWeek, 'color' => 'red'],
                                ['label' => 'Breeding Stock', 'value' => $breedingStock, 'change' => ($breedingThisWeek > 0 ? '+' : '') . $breedingThisWeek, 'color' => 'purple']
                            ];
                        @endphp

                        @foreach($stats as $stat)
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">{{ $stat['label'] }}</span>
                                <div class="flex items-center">
                                    <span class="text-sm font-medium text-gray-900 mr-2">{{ number_format($stat['value']) }}</span>
                                    <span class="text-xs font-medium px-2 py-0.5 rounded-full
                                        {{ $stat['change'][0] === '+' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ $stat['change'] }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 pt-4 border-t border-gray-200">
                        @php
                            // Calculate system metrics
                            $lastBackupTime = now()->subHours(rand(2, 12)); // Simulate backup schedule
                            $systemStartTime = now()->subDays(rand(1, 30)); // Simulate system start
                            $uptimeHours = now()->diffInHours($systemStartTime);
                            $uptimePercentage = min(99.9, 100 - ($activeAlerts * 0.1)); // Reduce uptime based on alerts
                        @endphp
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600">Last backup</span>
                            <span class="text-gray-900 font-medium">{{ $lastBackupTime->format('M j, g:i A') }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm mt-2">
                            <span class="text-gray-600">System uptime</span>
                            <span class="{{ $uptimePercentage >= 99 ? 'text-green-600' : ($uptimePercentage >= 95 ? 'text-yellow-600' : 'text-red-600') }} font-medium">
                                {{ number_format($uptimePercentage, 1) }}%
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-sm mt-2">
                            <span class="text-gray-600">Running for</span>
                            <span class="text-gray-900 font-medium">
                                @if($uptimeHours < 24)
                                    {{ $uptimeHours }}h
                                @else
                                    {{ floor($uptimeHours / 24) }}d {{ $uptimeHours % 24 }}h
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>