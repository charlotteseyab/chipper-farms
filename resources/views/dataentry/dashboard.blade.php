<x-app-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-900">Data Entry Dashboard</h1>
                <p class="text-gray-600 mt-2">Quick access to data entry tasks and recent activity</p>
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <a href="{{ route('all-stocks.create') }}" class="bg-green-600 hover:bg-green-700 text-white p-4 rounded-lg shadow-sm transition group">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium">Add New Pig</p>
                            <p class="text-xs opacity-90">Register new stock</p>
                        </div>
                    </div>
                </a>

                <a href="{{ route('weights.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white p-4 rounded-lg shadow-sm transition group">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium">Record Weight</p>
                            <p class="text-xs opacity-90">Log pig weights</p>
                        </div>
                    </div>
                </a>

                <a href="{{ route('mating.manage') }}" class="bg-pink-600 hover:bg-pink-700 text-white p-4 rounded-lg shadow-sm transition group">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium">Manage Mating</p>
                            <p class="text-xs opacity-90">Plan breeding</p>
                        </div>
                    </div>
                </a>

                <a href="{{ route('gestation.index') }}" class="bg-purple-600 hover:bg-purple-700 text-white p-4 rounded-lg shadow-sm transition group">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium">Update Gestation</p>
                            <p class="text-xs opacity-90">Track pregnancies</p>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Status Panels -->
            <div class="space-y-4 mb-8">
                @php
                    // Calculate all task data
                    $pigsNeedingWeighing = \App\Models\PigManagement::whereDoesntHave('weights', function($query) {
                        $query->where('created_at', '>=', now()->subDays(7));
                    })->where('date_of_birth', '<=', now()->subDays(7))->count();

                    $breedingChecks = \App\Models\PigManagement::where('stock_type', 'breeding')
                        ->whereDoesntHave('weights', function($query) {
                            $query->where('created_at', '>=', now()->subDays(14));
                        })->count();

                    $urgentAlerts = \App\Models\WeightAlert::where('severity', 'high')
                        ->where('acknowledged', false)->count();

                    $recentWeights = \App\Models\PigWeight::where('created_at', '>=', now()->subDays(7))->count();
                    $totalPigs = \App\Models\PigManagement::count();
                    $completionRate = $totalPigs > 0 ? round((($totalPigs - $pigsNeedingWeighing) / $totalPigs) * 100) : 0;
                @endphp

                <!-- Weekly Weighing Status Panel -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full {{ $pigsNeedingWeighing > 0 ? 'bg-red-500' : 'bg-green-500' }} mr-3"></div>
                            <h3 class="text-lg font-semibold text-gray-900">Weekly Weighing Schedule</h3>
                            <span class="ml-3 px-2 py-1 text-xs font-medium rounded-full {{ $pigsNeedingWeighing > 0 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                {{ $pigsNeedingWeighing }} pending
                            </span>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-gray-900">{{ $completionRate }}%</div>
                            <div class="text-sm text-gray-500">Complete</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="flex justify-between text-sm text-gray-600 mb-2">
                            <span>Progress: {{ $totalPigs - $pigsNeedingWeighing }}/{{ $totalPigs }} pigs weighed</span>
                            <span>{{ $recentWeights }} weights this week</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-500 h-2 rounded-full transition-all duration-300" style="width: {{ $completionRate }}%"></div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600">
                            @if($pigsNeedingWeighing > 0)
                                <span class="text-red-600 font-medium">{{ $pigsNeedingWeighing }} pigs</span> need weighing this week
                            @else
                                <span class="text-green-600 font-medium">All pigs</span> are up to date with weighing
                            @endif
                        </div>
                        <a href="{{ route('weights.create') }}" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition">
                            Record Weight
                        </a>
                    </div>
                </div>

                <!-- Breeding Health Monitoring Panel -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full {{ $breedingChecks > 0 ? 'bg-yellow-500' : 'bg-green-500' }} mr-3"></div>
                            <h3 class="text-lg font-semibold text-gray-900">Breeding Health Monitoring</h3>
                            <span class="ml-3 px-2 py-1 text-xs font-medium rounded-full {{ $breedingChecks > 0 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700' }}">
                                {{ $breedingChecks }} overdue
                            </span>
                        </div>
                        <div class="text-right">
                            @php
                                $breedingTotal = \App\Models\PigManagement::where('stock_type', 'breeding')->count();
                                $breedingCompletionRate = $breedingTotal > 0 ? round((($breedingTotal - $breedingChecks) / $breedingTotal) * 100) : 0;
                            @endphp
                            <div class="text-2xl font-bold text-gray-900">{{ $breedingCompletionRate }}%</div>
                            <div class="text-sm text-gray-500">Monitored</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="flex justify-between text-sm text-gray-600 mb-2">
                            <span>Breeding stock: {{ $breedingTotal - $breedingChecks }}/{{ $breedingTotal }} monitored</span>
                            <span>Last 14 days</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-yellow-500 h-2 rounded-full transition-all duration-300" style="width: {{ $breedingCompletionRate }}%"></div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600">
                            @if($breedingChecks > 0)
                                <span class="text-yellow-600 font-medium">{{ $breedingChecks }} breeding pigs</span> need health checks
                            @else
                                <span class="text-green-600 font-medium">All breeding stock</span> recently monitored
                            @endif
                        </div>
                        <a href="{{ route('all-stocks.index') }}?filter=breeding" class="px-4 py-2 bg-yellow-600 text-white text-sm rounded-lg hover:bg-yellow-700 transition">
                            Check Breeding Stock
                        </a>
                    </div>
                </div>

                <!-- Critical Alerts Panel -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full {{ $urgentAlerts > 0 ? 'bg-red-500' : 'bg-green-500' }} mr-3"></div>
                            <h3 class="text-lg font-semibold text-gray-900">Critical Weight Alerts</h3>
                            <span class="ml-3 px-2 py-1 text-xs font-medium rounded-full {{ $urgentAlerts > 0 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                {{ $urgentAlerts }} urgent
                            </span>
                        </div>
                        <div class="text-right">
                            @php
                                $totalActiveAlerts = \App\Models\WeightAlert::where('acknowledged', false)->count();
                                $alertResolutionRate = $totalActiveAlerts > 0 ? round((1 - ($urgentAlerts / $totalActiveAlerts)) * 100) : 100;
                            @endphp
                            <div class="text-2xl font-bold text-gray-900">{{ $totalActiveAlerts }}</div>
                            <div class="text-sm text-gray-500">Active</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        @php
                            $highAlerts = \App\Models\WeightAlert::where('severity', 'high')->where('acknowledged', false)->count();
                            $mediumAlerts = \App\Models\WeightAlert::where('severity', 'medium')->where('acknowledged', false)->count();
                            $lowAlerts = \App\Models\WeightAlert::where('severity', 'low')->where('acknowledged', false)->count();
                        @endphp
                        <div class="flex justify-between text-sm text-gray-600 mb-2">
                            <span>Alert breakdown: {{ $highAlerts }} high, {{ $mediumAlerts }} medium, {{ $lowAlerts }} low</span>
                            <span>{{ \App\Models\WeightAlert::where('created_at', '>=', now()->subDay())->count() }} new today</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            @if($totalActiveAlerts > 0)
                                <div class="flex h-2 rounded-full overflow-hidden">
                                    @if($highAlerts > 0)
                                        <div class="bg-red-500" style="width: {{ ($highAlerts / $totalActiveAlerts) * 100 }}%"></div>
                                    @endif
                                    @if($mediumAlerts > 0)
                                        <div class="bg-yellow-500" style="width: {{ ($mediumAlerts / $totalActiveAlerts) * 100 }}%"></div>
                                    @endif
                                    @if($lowAlerts > 0)
                                        <div class="bg-blue-500" style="width: {{ ($lowAlerts / $totalActiveAlerts) * 100 }}%"></div>
                                    @endif
                                </div>
                            @else
                                <div class="bg-green-500 h-2 rounded-full w-full"></div>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600">
                            @if($urgentAlerts > 0)
                                <span class="text-red-600 font-medium">{{ $urgentAlerts }} critical alerts</span> need immediate attention
                            @else
                                <span class="text-green-600 font-medium">No critical alerts</span> - system healthy
                            @endif
                        </div>
                        <a href="{{ route('weight-alerts.dashboard') }}" class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition">
                            View Alerts
                        </a>
                    </div>
                </div>

            </div>

            <!-- Task Management Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Priority Tasks -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">🎯 Priority Tasks</h3>
                        <span class="text-sm text-gray-500">{{ now()->format('M j, Y') }}</span>
                    </div>

                    <div class="space-y-3">
                        @php
                            $priorityTasks = [];

                            // High priority: Urgent alerts
                            if($urgentAlerts > 0) {
                                $priorityTasks[] = [
                                    'id' => 'urgent_alerts',
                                    'title' => "Address {$urgentAlerts} critical weight alerts",
                                    'priority' => 'high',
                                    'count' => $urgentAlerts,
                                    'action' => route('weight-alerts.dashboard'),
                                    'icon' => 'alert'
                                ];
                            }

                            // Medium priority: Overdue weighing
                            if($pigsNeedingWeighing > 0) {
                                $priorityTasks[] = [
                                    'id' => 'overdue_weighing',
                                    'title' => "Weigh {$pigsNeedingWeighing} pigs (overdue)",
                                    'priority' => 'medium',
                                    'count' => $pigsNeedingWeighing,
                                    'action' => route('weights.create'),
                                    'icon' => 'scale'
                                ];
                            }

                            // Medium priority: Breeding checks
                            if($breedingChecks > 0) {
                                $priorityTasks[] = [
                                    'id' => 'breeding_checks',
                                    'title' => "Health check {$breedingChecks} breeding pigs",
                                    'priority' => 'medium',
                                    'count' => $breedingChecks,
                                    'action' => route('all-stocks.index') . '?filter=breeding',
                                    'icon' => 'heart'
                                ];
                            }

                            // Low priority: Data entry
                            $newPigsToday = \App\Models\PigManagement::whereDate('created_at', today())->count();
                            if($newPigsToday > 0) {
                                $priorityTasks[] = [
                                    'id' => 'new_pigs',
                                    'title' => "Complete data for {$newPigsToday} new pigs",
                                    'priority' => 'low',
                                    'count' => $newPigsToday,
                                    'action' => route('all-stocks.index'),
                                    'icon' => 'plus'
                                ];
                            }
                        @endphp

                        @forelse($priorityTasks as $task)
                            <div class="flex items-center p-3 rounded-lg border {{ $task['priority'] === 'high' ? 'border-red-200 bg-red-50' : ($task['priority'] === 'medium' ? 'border-yellow-200 bg-yellow-50' : 'border-blue-200 bg-blue-50') }}">
                                <div class="flex-shrink-0 mr-3">
                                    <input type="checkbox" class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                                </div>

                                <div class="flex-grow">
                                    <div class="flex items-center">
                                        <span class="w-2 h-2 rounded-full mr-2 {{ $task['priority'] === 'high' ? 'bg-red-500' : ($task['priority'] === 'medium' ? 'bg-yellow-500' : 'bg-blue-500') }}"></span>
                                        <span class="text-sm font-medium text-gray-900">{{ $task['title'] }}</span>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Priority: {{ ucfirst($task['priority']) }} • {{ $task['count'] }} items
                                    </div>
                                </div>

                                <a href="{{ $task['action'] }}" class="flex-shrink-0 px-3 py-1 text-xs font-medium rounded {{ $task['priority'] === 'high' ? 'bg-red-600 text-white hover:bg-red-700' : ($task['priority'] === 'medium' ? 'bg-yellow-600 text-white hover:bg-yellow-700' : 'bg-blue-600 text-white hover:bg-blue-700') }} transition">
                                    Start
                                </a>
                            </div>
                        @empty
                            <div class="text-center py-8">
                                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <h4 class="text-lg font-medium text-gray-900 mb-2">All caught up!</h4>
                                <p class="text-gray-500">No priority tasks at the moment.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Recent Activity & Quick Stats -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">📊 Today's Progress</h3>
                        <!-- <div class="text-sm text-gray-500">{{ now()->format('g:i A') }}</div> -->
                    </div>

                    <!-- Today's Stats -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        @php
                            $todayWeights = \App\Models\PigWeight::whereDate('created_at', today())->count();
                            $todayPigs = \App\Models\PigManagement::whereDate('created_at', today())->count();
                            $todayAlerts = \App\Models\WeightAlert::whereDate('created_at', today())->count();

                            $weeklyTarget = 20; // Example weekly target
                            $weekProgress = round(($recentWeights / $weeklyTarget) * 100);
                        @endphp

                        <div class="text-center p-3 bg-blue-50 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">{{ $todayWeights }}</div>
                            <div class="text-xs text-blue-600">Weights Today</div>
                        </div>

                        <div class="text-center p-3 bg-green-50 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">{{ $todayPigs }}</div>
                            <div class="text-xs text-green-600">New Pigs</div>
                        </div>

                        <div class="text-center p-3 bg-purple-50 rounded-lg">
                            <div class="text-2xl font-bold text-purple-600">{{ $recentWeights }}</div>
                            <div class="text-xs text-purple-600">Week Total</div>
                        </div>

                        <div class="text-center p-3 bg-orange-50 rounded-lg">
                            <div class="text-2xl font-bold text-orange-600">{{ min($weekProgress, 100) }}%</div>
                            <div class="text-xs text-orange-600">Week Goal</div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="space-y-2">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Quick Actions</h4>

                        <a href="{{ route('weights.create') }}" class="flex items-center p-2 rounded-lg hover:bg-gray-50 transition group">
                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3 group-hover:bg-blue-200 transition">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                </svg>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-900">Record Weight</div>
                                <div class="text-xs text-gray-500">Log pig weight measurements</div>
                            </div>
                        </a>

                        <a href="{{ route('all-stocks.create') }}" class="flex items-center p-2 rounded-lg hover:bg-gray-50 transition group">
                            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3 group-hover:bg-green-200 transition">
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-900">Add New Pig</div>
                                <div class="text-xs text-gray-500">Register new livestock</div>
                            </div>
                        </a>

                        <a href="{{ route('all-stocks.index') }}" class="flex items-center p-2 rounded-lg hover:bg-gray-50 transition group">
                            <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center mr-3 group-hover:bg-purple-200 transition">
                                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v11a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5h2a2 2 0 012 2v11a2 2 0 01-2 2h-2a2 2 0 01-2-2V7a2 2 0 012-2z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/>
                                </svg>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-900">View All Stock</div>
                                <div class="text-xs text-gray-500">Browse pig inventory</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>


        </div>
    </div>
</x-app-layout>