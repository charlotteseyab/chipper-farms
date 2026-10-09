<x-app-layout>
    @if(($attentionFilter ?? '') === 'overdue')
        <div class="mx-auto mt-4 max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-3 rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900 sm:flex-row sm:items-center sm:justify-between">
                <div><strong>Showing overdue litters.</strong> The Ready Now list is limited to litters at least seven days beyond the configured weaning age.</div>
                <a href="{{ route('weaning.dashboard') }}" class="font-bold underline">Clear filter</a>
            </div>
        </div>
    @endif
<div class="container mx-auto px-4 py-2">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Weaning Management</h1>
            <p class="text-gray-600 mt-2">Track litters by mother's ear tag - from birth to maturity</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('all-stocks.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                View All Stock
            </a>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl shadow border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Ready Now</p>
                    <p class="text-3xl font-bold text-red-600">{{ $stats['ready_now'] }}<span class="text-lg font-normal text-gray-700"> litter(s)</span></p>
                    <p class="text-xs text-gray-500">{{ $stats['ready_now_total'] }} piglets ({{ $stats['overdue_count'] }} overdue)</p>
                </div>
                <div class="p-3 bg-red-100 rounded-full">
                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
            </div>
        </div>
        
        @if(($attentionFilter ?? '') !== 'overdue')
        <div class="bg-white p-6 rounded-xl shadow border-l-4 border-yellow-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Due Soon</p>
                    <p class="text-3xl font-bold text-yellow-600">{{ $stats['due_soon'] }}<span class="text-lg font-normal text-gray-700"> litter(s)</span></p>
                    <p class="text-xs text-gray-500">{{ $stats['due_soon_total'] }} piglets (Next 7 days)</p>
                </div>
                <div class="p-3 bg-yellow-100 rounded-full">
                    <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>
        @endif
        
        <!-- <div class="bg-white p-6 rounded-xl shadow border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">In Progress</p>
                    <p class="text-3xl font-bold text-blue-600">{{ $stats['in_progress'] }}</p>
                    <p class="text-xs text-gray-500">Weaned, growing</p>
                </div>
                <div class="p-3 bg-blue-100 rounded-full">
                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
            </div>
        </div> -->
        
        <!-- <div class="bg-white p-6 rounded-xl shadow border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Completed</p>
                    <p class="text-3xl font-bold text-green-600">{{ $stats['completed_total'] }}</p>
                    <p class="text-xs text-gray-500">{{ $stats['completed_this_month'] }} this month</p>
                </div>
                <div class="p-3 bg-green-100 rounded-full">
                    <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div> -->
    </div>

    <!-- Tabbed Interface -->
    <div class="bg-white rounded-xl shadow" x-data="{ activeTab: 'ready' }">
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200">
            <nav class="flex space-x-8 px-6" aria-label="Tabs">
            @if(($attentionFilter ?? '') !== 'overdue')
                <button @click="activeTab = 'soon'" :class="activeTab === 'soon' ? 'border-yellow-500 text-yellow-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Due Soon ({{ $stats['due_soon'] }})
                </button>
            @endif
                <button @click="activeTab = 'ready'" :class="activeTab === 'ready' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Ready Now ({{ $stats['ready_now'] }})
                </button>
                <!-- <button @click="activeTab = 'progress'" :class="activeTab === 'progress' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    In Progress ({{ $stats['in_progress'] }})
                </button>
                <button @click="activeTab = 'completed'" :class="activeTab === 'completed' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Completed ({{ $stats['completed_total'] }})
                </button> -->
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            <!-- READY NOW TAB -->
            <div x-show="activeTab === 'ready'" x-transition>
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Litters Ready for Weaning ({{ $weaningAge }}+ days old)</h3>
                    <p class="text-sm text-gray-600">These litters can be weaned immediately. Track by mother's ear tag and litter size.</p>
                </div>
                
                @if($readyNowLitters->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mother (Sow)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Litter Age</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Surviving Piglets</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Farrowing Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($readyNowLitters as $litter)
                                    <tr class="hover:bg-gray-50 @if($litter->is_critical) bg-red-50 @elseif($litter->is_overdue) bg-orange-50 @else bg-green-50 @endif">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-full @if($litter->is_critical) bg-red-200 @elseif($litter->is_overdue) bg-orange-200 @else bg-green-200 @endif flex items-center justify-center">
                                                        <span class="text-sm font-medium @if($litter->is_critical) text-red-800 @elseif($litter->is_overdue) text-orange-800 @else text-green-800 @endif">
                                                            Pig
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">{{ $litter->sow->ear_tag_number }}</div>
                                                    <div class="text-xs text-gray-500">{{ $litter->sow->code ?? 'N/A' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium @if($litter->is_critical) text-red-600 @elseif($litter->is_overdue) text-orange-600 @else text-green-600 @endif">
                                                {{ $litter->current_age }} days
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                @if($litter->days_overdue > 0)
                                                    {{ $litter->days_overdue }} days overdue
                                                @else
                                                    Ready now
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ $litter->surviving_piglets }}</div>
                                            <div class="text-xs text-gray-500">
                                                Born: {{ $litter->live_births ?? 0 }}
                                                @if($litter->pigletDeaths->sum('count') > 0)
                                                    • Deaths: {{ $litter->pigletDeaths->sum('count') }}
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $litter->farrowing_date->format('M d, Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($litter->is_critical)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    Critical Overdue
                                                </span>
                                            @elseif($litter->is_overdue)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                    Overdue
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    Ready
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <a href="{{ route('weaning.litter-preview', $litter) }}" class="inline-flex items-center px-3 py-1 bg-green-600 text-white text-xs rounded-md hover:bg-green-700">
                                                    <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                                    </svg>
                                                    Wean Litter
                                                </a>
                                                <a href="{{ route('pigs.show', $litter->sow->id) }}" class="inline-flex items-center px-3 py-1 bg-gray-600 text-white text-xs rounded-md hover:bg-gray-700">
                                                    View Sow
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="px-6 py-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing {{ $readyNowLitters->firstItem() }} to {{ $readyNowLitters->lastItem() }} of {{ $readyNowLitters->total() }} litters
                            </div>
                            <div class="flex items-center space-x-2">
                                <x-simple-pagination :paginator="$readyNowLitters" />
                            </div>
                        </div>
                    </div>
                @else
                    <div class="px-6 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No litters ready for weaning</h3>
                        <p class="mt-1 text-sm text-gray-500">All litters are either too young or already weaned.</p>
                    </div>
                @endif
            </div>

            <!-- DUE SOON TAB -->
            @if(($attentionFilter ?? '') !== 'overdue')
            <div x-show="activeTab === 'soon'" x-transition>
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Litters Due Soon (38-44 days old)</h3>
                    <p class="text-sm text-gray-600">Plan ahead for these upcoming weanings in the next 7 days.</p>
                </div>
                
                @if($dueSoonLitters->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mother (Sow)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Litter Age</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Days Until Weaning</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Surviving Piglets</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Farrowing Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($dueSoonLitters as $litter)
                                    <tr class="hover:bg-gray-50 bg-yellow-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-full bg-yellow-200 flex items-center justify-center">
                                                        <span class="text-sm font-medium text-yellow-800">
                                                            Pig
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">{{ $litter->sow->ear_tag_number }}</div>
                                                    <div class="text-xs text-gray-500">{{ $litter->sow->code ?? 'N/A' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-yellow-600 font-medium">
                                            {{ $litter->current_age }} days
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-yellow-600 font-medium">
                                            {{ $litter->days_until_weaning }} days
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ $litter->surviving_piglets }}</div>
                                            <div class="text-xs text-gray-500">
                                                Born: {{ $litter->live_births ?? 0 }}
                                                @if($litter->pigletDeaths->sum('count') > 0)
                                                    • Deaths: {{ $litter->pigletDeaths->sum('count') }}
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $litter->farrowing_date->format('M d, Y') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="px-6 py-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing {{ $dueSoonLitters->firstItem() }} to {{ $dueSoonLitters->lastItem() }} of {{ $dueSoonLitters->total() }} litters
                            </div>
                            <div class="flex items-center space-x-2">
                                <x-simple-pagination :paginator="$dueSoonLitters" />
                            </div>
                        </div>
                    </div>
                @else
                    <div class="px-6 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No litters due soon</h3>
                        <p class="mt-1 text-sm text-gray-500">No litters will reach weaning age in the next 7 days.</p>
                    </div>
                @endif
            </div>
            @endif

            <!-- IN PROGRESS TAB -->
            <div x-show="activeTab === 'progress'" x-transition>
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Weaned Animals (Growing to Maturity)</h3>
                    <p class="text-sm text-gray-600">Individual animals that have been weaned but haven't reached maturity ({{ $maturityAge }} days).</p>
                </div>
                
                @if($inProgress->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Animal</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Age</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Weaned</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Progress</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Weight</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($inProgress as $pig)
                                    @php
                                        $currentAge = (int) $pig->date_of_birth->diffInDays(now());
                                        $latestWeight = $pig->weights->first();
                                        $canComplete = $currentAge >= $maturityAge;
                                    @endphp
                                    <tr class="hover:bg-gray-50 bg-blue-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-full bg-blue-200 flex items-center justify-center">
                                                        <span class="text-sm font-medium text-blue-800">
                                                            {{ $pig->gender === 'M' ? 'Boar' : 'Pig' }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">{{ $pig->ear_tag_number }}</div>
                                                    <div class="text-xs text-gray-500">{{ $pig->code }} • {{ $pig->gender === 'M' ? 'Male' : 'Female' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600 font-medium">
                                            {{ $currentAge }} days
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @if($pig->weaning)
                                                <div class="font-medium">{{ $pig->weaning->weaning_date->format('M d, Y') }}</div>
                                                <div class="text-xs text-gray-500">{{ $pig->days_since_weaning }} days ago</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                                    <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $pig->maturity_progress }}%"></div>
                                                </div>
                                                <span class="text-xs text-gray-600">{{ number_format($pig->maturity_progress, 0) }}%</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @if($latestWeight)
                                                <div class="font-medium">{{ $latestWeight->weight }} kg</div>
                                                <div class="text-xs text-gray-500">{{ $latestWeight->weigh_date->format('M d') }}</div>
                                            @else
                                                <span class="text-gray-400">No weight</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                @if($canComplete)
                                                    <button onclick="completeMaturity({{ $pig->id }}, '{{ $pig->code }}')" class="inline-flex items-center px-3 py-1 bg-green-600 text-white text-xs rounded-md hover:bg-green-700">
                                                        Complete
                                                    </button>
                                                @endif
                                                <a href="{{ route('pigs.show', $pig->id) }}" class="inline-flex items-center px-3 py-1 bg-gray-600 text-white text-xs rounded-md hover:bg-gray-700">
                                                    View
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="px-6 py-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing {{ $inProgress->firstItem() }} to {{ $inProgress->lastItem() }} of {{ $inProgress->total() }} results
                            </div>
                            <div class="flex items-center space-x-2">
                                <x-simple-pagination :paginator="$inProgress" />
                            </div>
                        </div>
                    </div>
                @else
                    <div class="px-6 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No animals in progress</h3>
                        <p class="mt-1 text-sm text-gray-500">No animals are currently in the weaning-to-maturity phase.</p>
                    </div>
                @endif
            </div>

            <!-- COMPLETED TAB -->
            <div x-show="activeTab === 'completed'" x-transition>
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Completed Weaning Journey</h3>
                    <p class="text-sm text-gray-600">Animals that have completed the full weaning-to-maturity process.</p>
                </div>
                
                @if($completed->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Animal</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Completed</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Age at Maturity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destination</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Completed By</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($completed as $weaning)
                                    <tr class="hover:bg-gray-50 bg-green-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-full bg-green-200 flex items-center justify-center">
                                                        <span class="text-sm font-medium text-green-800">
                                                            {{ $weaning->pig->gender === 'M' ? 'Boar' : 'Pig' }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">{{ $weaning->pig?->identityAt($weaning->weaning_date) }}</div>
                                                    <div class="text-xs text-gray-500">{{ $weaning->pig->code }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $weaning->maturity_completion_date->format('M d, Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $weaning->age_at_maturity_days }} days
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                @if($weaning->next_destination === 'breeding') bg-purple-100 text-purple-800 
                                                @else bg-blue-100 text-blue-800 @endif">
                                                {{ ucfirst($weaning->next_destination ?? 'market') }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $weaning->completedBy->name ?? 'N/A' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="px-6 py-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing {{ $completed->firstItem() }} to {{ $completed->lastItem() }} of {{ $completed->total() }} results
                            </div>
                            <div class="flex items-center space-x-2">
                                <x-simple-pagination :paginator="$completed" />
                            </div>
                        </div>
                    </div>
                @else
                    <div class="px-6 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No completed records</h3>
                        <p class="mt-1 text-sm text-gray-500">No animals have completed the weaning journey yet.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
function completeMaturity(pigId, pigCode) {
    if (confirm(`Complete maturity for ${pigCode}? This will move the animal to the completed section.`)) {
        fetch(`/weaning/${pigId}/complete-maturity`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json',
            },
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error completing maturity: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while completing maturity.');
        });
    }
}

</script>
</x-app-layout>
