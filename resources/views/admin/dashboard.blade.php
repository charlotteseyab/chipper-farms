<x-app-layout>
    <div class="py-6 bg-gray-180 min-h-screen">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Filter Section -->
            <div class="bg-white rounded-lg shadow-sm border p-6 mb-8">
                <form method="GET" action="{{ route('admin.dashboard') }}" class="flex flex-wrap items-end gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <label for="date_from" class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                        <input type="date" 
                               id="date_from" 
                               name="date_from" 
                               value="{{ $dateFrom }}" 
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <label for="date_to" class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                        <input type="date" 
                               id="date_to" 
                               name="date_to" 
                               value="{{ $dateTo }}" 
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" 
                                class="px-6 py-2.5 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition-colors shadow-sm">
                            Apply Filter
                        </button>
                        <a href="{{ route('admin.dashboard') }}" 
                           class="px-6 py-2.5 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition-colors">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- ========== STOCK SECTION ========== -->
            <section class="mb-12">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Stock Overview</h2>
                        <p class="text-gray-600 text-sm mt-1">Current inventory and stock distribution</p>
                    </div>
                    <a href="{{ route('all-stocks.index') }}" class="text-green-600 hover:text-green-700 font-medium text-sm flex items-center gap-1">
                        View All <span>→</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                    <a href="{{ route('all-stocks.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-green-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-green-700 text-sm font-medium mb-1">Total Pigs</p>
                        <p class="text-3xl font-bold text-green-900">{{ number_format($totalPigs) }}</p>
                        <p class="text-green-600 text-xs mt-2">{{ number_format($weanedPigs) }} weaned • {{ number_format($pigletsCount) }} piglets</p>
                    </a>
                    <a href="{{ route('all-stocks.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-blue-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-blue-700 text-sm font-medium mb-1">Active Pigs</p>
                        <p class="text-3xl font-bold text-blue-900">{{ number_format($activePigs) }}</p>
                        <p class="text-blue-600 text-xs mt-2">{{ number_format($activeMaleCount) }} males • {{ number_format($activeFemaleCount) }} females</p>
                    </a>
                    <a href="{{ route('all-stocks.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-purple-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-purple-700 text-sm font-medium mb-1">Breeding Stock</p>
                        <p class="text-3xl font-bold text-purple-900">{{ number_format($breedingStock) }}</p>
                        <p class="text-purple-600 text-xs mt-2">{{ number_format($standardStock) }} standard stock</p>
                    </a>
                    <a href="{{ route('deaths.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-red-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-red-700 text-sm font-medium mb-1">Mortality Rate</p>
                        <p class="text-3xl font-bold text-red-900">{{ number_format($mortalityRate, 1) }}%</p>
                        <p class="text-red-600 text-xs mt-2">{{ number_format($deceasedPigs) }} deceased • {{ number_format($soldPigs) }} sold</p>
                    </a>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white rounded-lg p-6 shadow-sm border border-green-100">
                        <h3 class="text-lg font-semibold text-green-900 mb-4">Breed Distribution</h3>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between py-2 border-b border-green-200">
                                <span class="text-green-800 font-medium">LW (Large White)</span>
                                <span class="font-bold text-green-900 text-lg">{{ number_format($breedCounts['LW'] ?? 0) }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-green-200">
                                <span class="text-green-800 font-medium">DR (Duroc)</span>
                                <span class="font-bold text-green-900 text-lg">{{ number_format($breedCounts['DR'] ?? 0) }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2">
                                <span class="text-green-800 font-medium">LWDR (Large White Duroc)</span>
                                <span class="font-bold text-green-900 text-lg">{{ number_format($breedCounts['LWDR'] ?? 0) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg p-6 shadow-sm border border-blue-100">
                        <h3 class="text-lg font-semibold text-blue-900 mb-4">Stock Trends (6 Months)</h3>
                        <div class="h-64">
                            <canvas id="stockTrendsChart"></canvas>
                        </div>
                    </div>
                </div>
            </section>

            <div class="border-t-2 border-gray-300 my-8"></div>

            <!-- ========== MATING SECTION ========== -->
            <section class="mb-12">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Mating Management</h2>
                        <p class="text-gray-600 text-sm mt-1">Breeding activities and pregnancy tracking</p>
                    </div>
                    <a href="{{ route('mating.manage') }}" class="text-blue-600 hover:text-blue-700 font-medium text-sm flex items-center gap-1">
                        View All <span>→</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                    <a href="{{ route('mating.manage') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-blue-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-blue-700 text-sm font-medium mb-1">Total Matings</p>
                        <p class="text-3xl font-bold text-blue-900">{{ number_format($totalMatings) }}</p>
                        <p class="text-blue-600 text-xs mt-2">{{ number_format($plannedMatings) }} planned • {{ number_format($completedMatings) }} completed</p>
                    </a>
                    <a href="{{ route('mating.manage') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-green-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-green-700 text-sm font-medium mb-1">Pregnant</p>
                        <p class="text-3xl font-bold text-green-900">{{ number_format($pregnantMatings) }}</p>
                        <p class="text-green-600 text-xs mt-2">{{ number_format($pregnancyRate, 1) }}% pregnancy rate</p>
                    </a>
                    <a href="{{ route('mating.manage') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-yellow-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-yellow-700 text-sm font-medium mb-1">Checks Due</p>
                        <p class="text-3xl font-bold text-yellow-900">{{ number_format($pregnancyChecksDue) }}</p>
                        <p class="text-yellow-600 text-xs mt-2">{{ number_format($pregnancyChecksOverdue) }} overdue</p>
                    </a>
                    <a href="{{ route('mating.manage') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-purple-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-purple-700 text-sm font-medium mb-1">Fertility Rate</p>
                        <p class="text-3xl font-bold text-purple-900">{{ number_format($fertilityRate, 1) }}%</p>
                        <p class="text-purple-600 text-xs mt-2">{{ number_format($upcomingMatings) }} upcoming</p>
                    </a>
                </div>

                <div class="bg-white rounded-lg p-6 shadow-sm border border-blue-100">
                    <h3 class="text-lg font-semibold text-blue-900 mb-4">Mating Activity (6 Months)</h3>
                    <div class="h-64" style="position: relative; width: 100%; height: 300px;"> 
                        <canvas id="matingTrendsChart"></canvas>
                    </div>
                </div>
            </section>

            <div class="border-t-2 border-gray-300 my-8"></div>

            <!-- ========== GESTATION SECTION ========== -->
            <section class="mb-12">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Gestation Tracking</h2>
                        <p class="text-gray-600 text-sm mt-1">Pregnancy monitoring and farrowing preparation</p>
                    </div>
                    <a href="{{ route('gestation.index') }}" class="text-indigo-600 hover:text-indigo-700 font-medium text-sm flex items-center gap-1">
                        View All <span>→</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                    <a href="{{ route('gestation.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-indigo-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-indigo-700 text-sm font-medium mb-1">Pregnant</p>
                        <p class="text-3xl font-bold text-indigo-900">{{ number_format($totalPregnant) }}</p>
                        <p class="text-indigo-600 text-xs mt-2">{{ number_format($avgGestationDays, 1) }} avg days</p>
                    </a>
                    <a href="{{ route('gestation.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-green-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-green-700 text-sm font-medium mb-1">Due This Week</p>
                        <p class="text-3xl font-bold text-green-900">{{ number_format($dueThisWeek) }}</p>
                        <p class="text-yellow-600 text-xs mt-2">{{ number_format($dueNextWeek) }} due next week</p>
                    </a>
                    <a href="{{ route('gestation.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-red-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-red-700 text-sm font-medium mb-1">Overdue</p>
                        <p class="text-3xl font-bold text-red-900">{{ number_format($overdueGestation) }}</p>
                        <p class="text-red-600 text-xs mt-2">Requires attention</p>
                    </a>
                    <a href="{{ route('farrowing.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-purple-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-purple-700 text-sm font-medium mb-1">Farrowed This Month</p>
                        <p class="text-3xl font-bold text-purple-900">{{ number_format($farrowedThisMonth) }}</p>
                        <p class="text-purple-600 text-xs mt-2">{{ number_format($totalFarrowed) }} total</p>
                    </a>
                </div>

                <div class="bg-white rounded-lg p-6 shadow-sm border border-indigo-100">
                    <h3 class="text-lg font-semibold text-indigo-900 mb-4">Gestation Status Distribution</h3>
                    <div class="h-64" style="position: center; width: 100%; height: 300px;"> 
                        <canvas id="gestationStatusChart"></canvas>
                    </div>
                </div>
            </section>

            <div class="border-t-2 border-gray-300 my-8"></div>

            <!-- ========== FARROWING SECTION ========== -->
            <section class="mb-12">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Farrowing Records</h2>
                        <p class="text-gray-600 text-sm mt-1">Birth records and piglet statistics</p>
                    </div>
                    <a href="{{ route('farrowing.index') }}" class="text-pink-600 hover:text-pink-700 font-medium text-sm flex items-center gap-1">
                        View All <span>→</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                    <a href="{{ route('farrowing.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-pink-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-pink-700 text-sm font-medium mb-1">Total Farrowings</p>
                        <p class="text-3xl font-bold text-pink-900">{{ number_format($totalFarrowings) }}</p>
                        <p class="text-pink-600 text-xs mt-2">{{ number_format($farrowingsThisMonth) }} this month</p>
                    </a>
                    <a href="{{ route('farrowing.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-green-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-green-700 text-sm font-medium mb-1">Total Born</p>
                        <p class="text-3xl font-bold text-green-900">{{ number_format($totalBorn) }}</p>
                        <p class="text-green-600 text-xs mt-2">{{ number_format($avgLitterSize, 1) }} avg litter size</p>
                    </a>
                    <a href="{{ route('farrowing.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-blue-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-blue-700 text-sm font-medium mb-1">Survival Rate</p>
                        <p class="text-3xl font-bold text-blue-900">{{ number_format($survivalRate, 1) }}%</p>
                        <p class="text-blue-600 text-xs mt-2">{{ number_format($avgStillborns, 1) }} avg stillborns</p>
                    </a>
                    <a href="{{ route('farrowing.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-purple-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-purple-700 text-sm font-medium mb-1">Avg Birth Weight</p>
                        <p class="text-3xl font-bold text-purple-900">{{ number_format($avgBirthWeight ?? 0, 2) }} kg</p>
                        <p class="text-purple-600 text-xs mt-2">{{ number_format($farrowingsThisYear) }} this year</p>
                    </a>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white rounded-lg p-6 shadow-sm border border-pink-100">
                        <h3 class="text-lg font-semibold text-pink-900 mb-4">Farrowing Trends (6 Months)</h3>
                        <div class="h-64">
                            <canvas id="farrowingTrendsChart"></canvas>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg p-6 shadow-sm border border-green-100">
                        <h3 class="text-lg font-semibold text-green-900 mb-4">Piglets Born (6 Months)</h3>
                        <div class="h-64">
                            <canvas id="pigletsBornChart"></canvas>
                        </div>
                    </div>
                </div>
            </section>

            <div class="border-t-2 border-gray-300 my-8"></div>

            <!-- ========== WEANING SECTION ========== -->
            <section class="mb-12">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Weaning Management</h2>
                        <p class="text-gray-600 text-sm mt-1">Piglet weaning and maturity tracking</p>
                    </div>
                    <a href="{{ route('weaning.dashboard') }}" class="text-teal-600 hover:text-teal-700 font-medium text-sm flex items-center gap-1">
                        View All <span>→</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                    <a href="{{ route('weaning.dashboard') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-teal-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-teal-700 text-sm font-medium mb-1">Total Weaned</p>
                        <p class="text-3xl font-bold text-teal-900">{{ number_format($totalWeaned) }}</p>
                        <p class="text-teal-600 text-xs mt-2">{{ number_format($weaningPercentage, 1) }}% weaning rate</p>
                    </a>
                    <a href="{{ route('weaning.dashboard') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-green-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-green-700 text-sm font-medium mb-1">Ready to Wean</p>
                        <p class="text-3xl font-bold text-green-900">{{ number_format($readyToWean) }}</p>
                        <p class="text-green-600 text-xs mt-2">{{ number_format($dueSoonWeaning) }} due soon</p>
                    </a>
                    <a href="{{ route('weaning.dashboard') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-red-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-red-700 text-sm font-medium mb-1">Overdue</p>
                        <p class="text-3xl font-bold text-red-900">{{ number_format($overdueWeaning) }}</p>
                        <p class="text-red-600 text-xs mt-2">Past {{ $weaningAge + 7 }} days</p>
                    </a>
                    <a href="{{ route('weaning.dashboard') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-purple-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-purple-700 text-sm font-medium mb-1">Avg Weaning</p>
                        <p class="text-3xl font-bold text-purple-900">{{ number_format($avgWeaningAge ?? 0, 0) }} days</p>
                        <p class="text-purple-600 text-xs mt-2">{{ number_format($avgWeaningWeight ?? 0, 2) }} kg avg weight</p>
                    </a>
                </div>

                <div class="bg-white rounded-lg p-6 shadow-sm border border-teal-100">
                    <h3 class="text-lg font-semibold text-teal-900 mb-4">Weaning Activity (6 Months)</h3>
                    <div class="h-64" style="position: relative; width: 100%; height: 300px;"> 
                        <canvas id="weaningTrendsChart"></canvas>
                    </div>
                </div>
            </section>

            <div class="border-t-2 border-gray-300 my-8"></div>

            <!-- ========== WEIGHT MANAGEMENT SECTION ========== -->
            <section class="mb-12">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Weight Management</h2>
                        <p class="text-gray-600 text-sm mt-1">Weight tracking and growth monitoring</p>
                    </div>
                    <a href="{{ route('weights.index') }}" class="text-orange-600 hover:text-orange-700 font-medium text-sm flex items-center gap-1">
                        View All <span>→</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                    <a href="{{ route('weights.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-orange-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-orange-700 text-sm font-medium mb-1">Total Records</p>
                        <p class="text-3xl font-bold text-orange-900">{{ number_format($totalWeights) }}</p>
                        <p class="text-orange-600 text-xs mt-2">{{ number_format($weightsThisWeek) }} this week</p>
                    </a>
                    <a href="{{ route('weights.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-red-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-red-700 text-sm font-medium mb-1">Overdue</p>
                        <p class="text-3xl font-bold text-red-900">{{ number_format($overdueForWeighing) }}</p>
                        <p class="text-red-600 text-xs mt-2">{{ number_format($dueForWeighing) }} due soon</p>
                    </a>
                    <a href="{{ route('weights.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-blue-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-blue-700 text-sm font-medium mb-1">Avg Weight</p>
                        <p class="text-3xl font-bold text-blue-900">{{ number_format($avgWeight ?? 0, 1) }} kg</p>
                        <p class="text-blue-600 text-xs mt-2">{{ number_format($totalWeight ?? 0, 1) }} kg total</p>
                    </a>
                    <a href="{{ route('weights.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-green-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-green-700 text-sm font-medium mb-1">Farm Value</p>
                        <p class="text-3xl font-bold text-green-900">₵{{ number_format($totalFarmValue ?? 0, 0) }}</p>
                        <p class="text-green-600 text-xs mt-2">₵{{ number_format($liveWeightPrice, 2) }}/kg</p>
                    </a>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="bg-white rounded-lg p-6 shadow-sm border border-orange-100">
                        <h3 class="text-lg font-semibold text-orange-900 mb-4">Weight Trends (30 Days)</h3>
                        <div class="h-64">
                            <canvas id="weightTrendsChart"></canvas>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg p-6 shadow-sm border border-blue-100">
                        <h3 class="text-lg font-semibold text-blue-900 mb-4">Average Weight (6 Months)</h3>
                        <div class="h-64">
                            <canvas id="weightMonthlyChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Weighing Alerts Card -->
                <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-lg p-6 shadow-sm border-l-4 border-red-500">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-red-900">Weighing Alerts</h3>
                        <button onclick="toggleWeighingAlertsModal()" class="text-red-700 hover:text-red-900 font-medium text-sm flex items-center gap-1">
                            View All <span>→</span>
                        </button>
                    </div>
                    @if($overduePigsData->count() > 0)
                    <div class="space-y-2 mb-4">
                        @foreach($overduePigsData->take(5) as $item)
                        <div class="flex items-center justify-between bg-white rounded-lg p-3 hover:bg-red-50 transition-colors border border-red-200">
                            <div>
                                <p class="font-medium text-gray-900">{{ $item['pig']->ear_tag_number ?? 'N/A' }}</p>
                                <p class="text-sm text-gray-600">{{ $item['days_since'] }} days since last weighing</p>
                            </div>
                            <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-medium">
                                {{ $item['overdue_days'] }} days overdue
                            </span>
                        </div>
                        @endforeach
                    </div>
                    <div class="text-center">
                        <button onclick="toggleWeighingAlertsModal()" class="w-full px-4 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition-colors">
                            View All {{ $allOverduePigsData->count() }} Overdue Pigs
                        </button>
                    </div>
                    @else
                    <div class="text-center py-4">
                        <p class="text-red-700">All pigs are up to date with weighing!</p>
                    </div>
                    @endif
                </div>
            </section>

            <div class="border-t-2 border-gray-300 my-8"></div>

            <!-- ========== FEED MANAGEMENT SECTION ========== -->
            <section class="mb-12">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Feed Management</h2>
                        <p class="text-gray-600 text-sm mt-1">Inventory, scheduling, and procurement</p>
                    </div>
                    <a href="{{ route('feed-inventory.index') }}" class="text-amber-600 hover:text-amber-700 font-medium text-sm flex items-center gap-1">
                        View All <span>→</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                    <a href="{{ route('feed-inventory.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-amber-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-amber-700 text-sm font-medium mb-1">Low Stock Items</p>
                        <p class="text-3xl font-bold text-amber-900">{{ number_format($lowStockCount) }}</p>
                        <p class="text-amber-600 text-xs mt-2">{{ number_format($totalFeedItems) }} total items</p>
                    </a>
                    <a href="{{ route('feed-purchase-requests.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-blue-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-blue-700 text-sm font-medium mb-1">Pending Requests</p>
                        <p class="text-3xl font-bold text-blue-900">{{ number_format($pendingRequests) }}</p>
                        <p class="text-blue-600 text-xs mt-2">₵{{ number_format($pendingRequestValue, 0) }} value</p>
                    </a>
                    <a href="{{ route('feeds.schedule') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-green-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-green-700 text-sm font-medium mb-1">Fed Today</p>
                        <p class="text-3xl font-bold text-green-900">{{ number_format($fedToday) }}</p>
                        <p class="text-green-600 text-xs mt-2">{{ number_format($scheduledFeeds) }} scheduled</p>
                    </a>
                    <a href="{{ route('feed-inventory.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-purple-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-purple-700 text-sm font-medium mb-1">Total Inventory</p>
                        <p class="text-3xl font-bold text-purple-900">{{ number_format($totalFeedQuantity, 0) }}</p>
                        <p class="text-purple-600 text-xs mt-2">{{ number_format($totalScheduled) }} scheduled</p>
                    </a>
                </div>

                <div class="bg-white rounded-lg p-6 shadow-sm border">
                    <h3 class="text-lg font-semibold text-amber-900 mb-4">Feed Consumption Trends (6 Months)</h3>
                    <div class="h-64" style="position: center; width: 100%; height: 300px;"> 
                        <canvas id="feedTrendsChart"></canvas>
                    </div>
                </div>
            </section>

            <div class="border-t-2 border-gray-300 my-8"></div>

            <!-- ========== HEALTH & PERFORMANCE SECTION ========== -->
            <section class="mb-12">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Health & Performance</h2>
                        <p class="text-gray-600 text-sm mt-1">Health records and medication administration</p>
                    </div>
                    <a href="{{ route('health.index') }}" class="text-rose-600 hover:text-rose-700 font-medium text-sm flex items-center gap-1">
                        View All <span>→</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                    <a href="{{ route('health.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-rose-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-rose-700 text-sm font-medium mb-1">Health Records</p>
                        <p class="text-3xl font-bold text-rose-900">{{ number_format($totalHealthRecords) }}</p>
                        <p class="text-rose-600 text-xs mt-2">{{ number_format($healthRecordsThisMonth) }} this month</p>
                    </a>
                    <a href="{{ route('medications.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-blue-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-blue-700 text-sm font-medium mb-1">Medications</p>
                        <p class="text-3xl font-bold text-blue-900">{{ number_format($totalMedications) }}</p>
                        <p class="text-blue-600 text-xs mt-2">{{ number_format($medicationsThisMonth) }} this month</p>
                    </a>
                    <a href="{{ route('health.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-yellow-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-yellow-700 text-sm font-medium mb-1">Critical Alerts</p>
                        <p class="text-3xl font-bold text-yellow-900">{{ number_format($criticalAlerts) }}</p>
                        <p class="text-yellow-600 text-xs mt-2">{{ number_format($totalAlerts) }} total alerts</p>
                    </a>
                    <a href="{{ route('sales.index') }}" class="bg-white rounded-lg p-6 shadow-sm border-l-4 border-green-500 hover:shadow-md transition-shadow cursor-pointer">
                        <p class="text-green-700 text-sm font-medium mb-1">Total Sales</p>
                        <p class="text-3xl font-bold text-green-900">{{ number_format($totalSales) }}</p>
                        <p class="text-green-600 text-xs mt-2">₵{{ number_format($totalRevenue, 0) }} revenue</p>
                    </a>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white rounded-lg p-6 shadow-sm border">
                        <h3 class="text-lg font-semibold text-rose-900 mb-4">Top Health Conditions</h3>
                        <div class="space-y-3">
                            @forelse($topConditions as $condition)
                            <div class="flex items-center justify-between py-2 border-b border-rose-200 last:border-0">
                                <span class="text-rose-800 font-medium">{{ $condition->condition }}</span>
                                <span class="font-bold text-rose-900">{{ $condition->count }} cases</span>
                            </div>
                            @empty
                            <p class="text-rose-600 text-sm">No health records yet</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="bg-white rounded-lg p-6 shadow-sm border">
                        <h3 class="text-lg font-semibold text-blue-900 mb-4">Health Activity (6 Months)</h3>
                        <div class="h-64">
                            <canvas id="healthTrendsChart"></canvas>
                        </div>
                    </div>
                </div>
            </section>

        </div>
        <x-copyright-minimal />
    </div>

    <!-- Weighing Alerts Modal -->
    <div id="weighingAlertsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-red-50 to-red-100">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Weighing Alerts Details</h2>
                    <p class="text-sm text-gray-600 mt-1">Pigs overdue for weighing with detailed information</p>
                </div>
                <button onclick="toggleWeighingAlertsModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
                @if($allOverduePigsData->count() > 0)
                    <div class="space-y-4">
                        @foreach($allOverduePigsData as $item)
                            @php
                                $pig = $item['pig'];
                                $weighingStatus = $item['weighing_status'];
                                $daysSince = $item['days_since'];
                                $lastWeighing = $item['last_weighing'];
                                $lastWeight = $item['last_weight'];
                                $overdueDays = $item['overdue_days'];
                                $isCritical = $overdueDays > 14;
                                $isOverdue = $overdueDays > 7;
                            @endphp
                            <div class="rounded-lg p-4 hover:shadow-md transition-shadow {{ $isCritical ? 'bg-red-50 border border-red-200' : ($isOverdue ? 'bg-orange-50 border border-orange-200' : 'bg-yellow-50 border border-yellow-200') }}">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <span class="text-2xl mr-3">
                                                @if($isCritical) ⚠️
                                                @elseif($isOverdue) ⏰
                                                @else 📅
                                                @endif
                                            </span>
                                            <div>
                                                <h3 class="text-lg font-semibold text-gray-900">{{ $pig->ear_tag_number ?? 'N/A' }}</h3>
                                                <p class="text-sm text-gray-600">
                                                    {{ $pig->breed ?? 'N/A' }} • {{ $pig->gender ?? 'N/A' }} • {{ ucfirst($pig->stock_type ?? 'N/A') }}
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Last Weighing</p>
                                                <p class="text-sm font-semibold text-gray-900">
                                                    @if($lastWeighing)
                                                        {{ $lastWeighing->format('M j, Y') }}
                                                    @else
                                                        Never weighed
                                                    @endif
                                                </p>
                                            </div>
                                            
                                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Last Weight</p>
                                                <p class="text-sm font-semibold text-gray-900">
                                                    @if($lastWeight)
                                                        {{ number_format($lastWeight->weight, 1) }} {{ $lastWeight->weight_unit ?? 'kg' }}
                                                    @else
                                                        No weight recorded
                                                    @endif
                                                </p>
                                            </div>
                                            
                                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Overdue By</p>
                                                <p class="text-sm font-semibold {{ $isCritical ? 'text-red-700' : ($isOverdue ? 'text-orange-700' : 'text-yellow-700') }}">
                                                    {{ max(0, (int)$overdueDays) }} days
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3 flex items-center justify-between">
                                            <div class="flex items-center">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $isCritical ? 'bg-red-100 text-red-800' : ($isOverdue ? 'bg-orange-100 text-orange-800' : 'bg-yellow-100 text-yellow-800') }}">
                                                    @if($isCritical) Critical
                                                    @elseif($isOverdue) Overdue
                                                    @else Due Soon
                                                    @endif
                                                </span>
                                                <span class="ml-2 text-xs text-gray-500">
                                                    {{ (int)$daysSince }} days since last weighing
                                                </span>
                                            </div>
                                            
                                            @if($pig)
                                            <a href="{{ route('weights.create', ['pig_id' => $pig->id]) }}" 
                                               class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white {{ $isCritical ? 'bg-red-600 hover:bg-red-700' : ($isOverdue ? 'bg-orange-600 hover:bg-orange-700' : 'bg-yellow-600 hover:bg-yellow-700') }} transition-colors">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                                </svg>
                                                Record Weight
                                            </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">All caught up!</h3>
                        <p class="text-gray-500">All pigs are up to date with their weighing schedule.</p>
                    </div>
                @endif
            </div>
            
            <div class="flex items-center justify-between p-6 border-t border-gray-200 bg-gray-50">
                <div class="text-sm text-gray-600">
                    <span class="font-medium">{{ $allOverduePigsData->count() }}</span> pigs need weighing attention
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('weights.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        View All Weights
                    </a>
                    <button onclick="toggleWeighingAlertsModal()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-600 hover:bg-gray-700 transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Stock Trends Chart
        new Chart(document.getElementById('stockTrendsChart'), {
            type: 'line',
            data: {
                labels: @json($stockTrendsLabels),
                datasets: [
                    {
                        label: 'Active',
                        data: @json($stockTrendsActive),
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Sold',
                        data: @json($stockTrendsSold),
                        borderColor: 'rgb(168, 85, 247)',
                        backgroundColor: 'rgba(168, 85, 247, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Deceased',
                        data: @json($stockTrendsDeceased),
                        borderColor: 'rgb(239, 68, 68)',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true } }
            }
        });

        // Mating Trends Chart
        new Chart(document.getElementById('matingTrendsChart'), {
            type: 'bar',
            data: {
                labels: @json($matingTrendsLabels),
                datasets: [{
                    label: 'Matings',
                    data: @json($matingTrendsData),
                    backgroundColor: 'rgba(59, 130, 246, 0.6)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                // 1. Set this to false to let the chart fill the container's height/width
                maintainAspectRatio: false, 
                plugins: { 
                    legend: { display: false } 
                },
                scales: { 
                    y: { 
                        beginAtZero: true,
                        // Optional: clean up the grid lines for a more "full width" look
                        grid: {
                            display: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                // Optional: reduce padding to touch the edges of the card
                layout: {
                    padding: 0
                }
            }
        });

        // Gestation Status Chart
        new Chart(document.getElementById('gestationStatusChart'), {
            type: 'doughnut', 
            data: {
                labels: Object.keys(@json($gestationStatusData)),
                datasets: [{
                    data: Object.values(@json($gestationStatusData)),
                    backgroundColor: [
                        'rgba(99, 102, 241, 0.8)', // Indigo
                        'rgba(34, 197, 94, 0.8)',  // Green
                        'rgba(245, 158, 11, 0.6)',  // yellow
                        'rgba(239, 68, 68, 0.8)'   // Red
                    ],
                    hoverOffset: 4,
                    borderWidth: 2,
                    borderColor: '#ffffff' // Adds a clean gap between segments
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Allows the CSS container to dictate size
                plugins: { 
                    legend: { 
                        position: 'bottom',
                        align: 'center', // Ensures the legend is also centered
                        labels: {
                            padding: 20,
                            usePointStyle: true // Circular legend icons look cleaner
                        }
                    } 
                },
                layout: {
                    padding: 10 // Gives the chart room to breathe so it doesn't touch the edges
                }
            }
        });

        // Farrowing Trends Chart
        new Chart(document.getElementById('farrowingTrendsChart'), {
            type: 'line',
            data: {
                labels: @json($farrowingTrendsLabels),
                datasets: [{
                    label: 'Farrowings',
                    data: @json($farrowingTrendsData),
                    borderColor: 'rgb(236, 72, 153)',
                    backgroundColor: 'rgba(236, 72, 153, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });

        // Piglets Born Chart
        new Chart(document.getElementById('pigletsBornChart'), {
            type: 'bar',
            data: {
                labels: @json($farrowingTrendsLabels),
                datasets: [{
                    label: 'Piglets Born',
                    data: @json($farrowingTrendsBorn),
                    backgroundColor: 'rgba(34, 197, 94, 0.6)',
                    borderColor: 'rgb(34, 197, 94)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });

        // Weaning Trends Chart
        new Chart(document.getElementById('weaningTrendsChart'), {
            type: 'line',
            data: {
                labels: @json($weaningTrendsLabels),
                datasets: [{
                    label: 'Weanings',
                    data: @json($weaningTrendsData),
                    borderColor: 'rgb(20, 184, 166)',
                    backgroundColor: 'rgba(20, 184, 166, 0.1)',
                    tension: 0.4,
                    fill: true, // This fills the area under the curve with your teal color
                    pointRadius: 4,
                    pointBackgroundColor: 'rgb(20, 184, 166)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Allows it to stretch to the container width
                plugins: { 
                    legend: { display: false } 
                },
                scales: { 
                    y: { 
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)' // Light grid lines for a cleaner look
                        }
                    },
                    x: {
                        grid: {
                            display: false // Hide vertical lines to make it look wider
                        }
                    }
                },
                layout: {
                    padding: {
                        left: 0,
                        right: 10,
                        top: 10,
                        bottom: 0
                    }
                }
            }
        });

        // Weight Trends Chart
        new Chart(document.getElementById('weightTrendsChart'), {
            type: 'line',
            data: {
                labels: @json($weightLabels),
                datasets: [{
                    label: 'Avg Weight (kg)',
                    data: @json($weightValues),
                    borderColor: 'rgb(249, 115, 22)',
                    backgroundColor: 'rgba(249, 115, 22, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: false } }
            }
        });

        // Weight Monthly Chart
        new Chart(document.getElementById('weightMonthlyChart'), {
            type: 'bar',
            data: {
                labels: @json($weightMonthlyLabels),
                datasets: [{
                    label: 'Avg Weight (kg)',
                    data: @json($weightMonthlyAvg),
                    backgroundColor: 'rgba(59, 130, 246, 0.6)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: false } }
            }
        });

        // Feed Trends Chart
        new Chart(document.getElementById('feedTrendsChart'), {
            type: 'line',
            data: {
                labels: @json($feedTrendsLabels),
                datasets: [
                    {
                        label: 'Scheduled',
                        data: @json($feedTrendsScheduled),
                        borderColor: 'rgb(245, 158, 11)',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)', // Amber
                        tension: 0.4,
                        fill: true // Helps visualize the volume of scheduled feed
                    },
                    {
                        label: 'Fed',
                        data: @json($feedTrendsFed),
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)', // Green
                        tension: 0.4,
                        fill: true // Helps visualize the actual consumption
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Forces the chart to fill the 100% width of the card
                plugins: { 
                    legend: { 
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    } 
                },
                scales: { 
                    y: { 
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        title: {
                            display: true,
                            text: 'Quantity (kg)' // Adds context to the numbers
                        }
                    },
                    x: {
                        grid: {
                            display: false 
                        }
                    }
                },
                layout: {
                    padding: {
                        top: 10,
                        bottom: 10,
                        left: 0,
                        right: 10
                    }
                }
            }
        });

        // Health Trends Chart
        new Chart(document.getElementById('healthTrendsChart'), {
            type: 'line',
            data: {
                labels: @json($healthTrendsLabels),
                datasets: [
                    {
                        label: 'Health Records',
                        data: @json($healthTrendsRecords),
                        borderColor: 'rgb(244, 63, 94)',
                        backgroundColor: 'rgba(244, 63, 94, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Medications',
                        data: @json($healthTrendsMedications),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>

    <script>
        function toggleWeighingAlertsModal() {
            const modal = document.getElementById('weighingAlertsModal');
            modal.classList.toggle('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('weighingAlertsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                toggleWeighingAlertsModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('weighingAlertsModal');
                if (modal && !modal.classList.contains('hidden')) {
                    toggleWeighingAlertsModal();
                }
            }
        });
    </script>
</x-app-layout>
