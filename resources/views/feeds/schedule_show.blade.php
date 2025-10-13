<x-app-layout>
    <div class="min-h-screen bg-gray-50">
        
        <!-- Header -->
        <div class="bg-white shadow-md border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <a href="{{ route('feeds.schedule') }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium flex items-center mb-2">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back to Schedule
                </a>
                <h1 class="text-3xl font-extrabold text-gray-900 leading-tight">Feed Schedule Details</h1>
                <p class="text-gray-500 text-sm mt-1">Detailed audit of the feeding event scheduled for {{ $entry->scheduled_for->format('M d, Y') }}.</p>
            </div>
        </div>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Status & Action Card (Left Column) -->
                <div class="lg:col-span-1 space-y-6">

                    <!-- Status Summary -->
                    <div class="bg-white rounded-xl shadow-xl border border-gray-100 p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                            <svg class="w-6 h-6 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.001 12.001 0 002.944 12c.045 2.502.684 4.908 1.834 7.072A11.95 11.95 0 0112 21.056c2.502-.045 4.908-.684 7.072-1.834A12.001 12.001 0 0021.056 12a11.955 11.955 0 01-1.096-4.016z"></path></svg>
                            Event Status
                        </h2>
                        
                        @php
                            $status = $entry->status;
                            $statusData = [
                                'planned' => ['class' => 'bg-yellow-100 text-yellow-800', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                                'fed' => ['class' => 'bg-green-100 text-green-800', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.001 12.001 0 002.944 12c.045 2.502.684 4.908 1.834 7.072A11.95 11.95 0 0112 21.056c2.502-.045 4.908-.684 7.072-1.834A12.001 12.001 0 0021.056 12a11.955 11.955 0 01-1.096-4.016z'],
                                'skipped' => ['class' => 'bg-red-100 text-red-800', 'icon' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z']
                            ];
                            $data = $statusData[$status] ?? $statusData['planned'];
                        @endphp
                        
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-4 py-1.5 rounded-full text-lg font-bold {{ $data['class'] }} uppercase shadow-lg">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $data['icon'] }}"></path></svg>
                                {{ ucfirst($status) }}
                            </span>
                        </div>
                        
                        {{-- This block now correctly relies on the newly populated skip_reason --}}
                        @if ($status === 'skipped' && $entry->skip_reason)
                            <div class="mt-4 pt-4 border-t border-gray-100">
                                <h4 class="text-sm font-semibold text-red-700 mb-2">Reason for Skipping:</h4>
                                <p class="text-sm text-gray-700 bg-red-50 p-3 rounded-lg border border-red-200 italic">
                                    {{ $entry->skip_reason }}
                                </p>
                            </div>
                        @endif
                    </div>

                    <!-- Audit Information -->
                    <div class="bg-white rounded-xl shadow-xl border border-gray-100 p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                            <svg class="w-6 h-6 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            Audit Log
                        </h2>
                        
                        <dl class="text-sm text-gray-600 space-y-3">
                            <div>
                                <dt class="font-medium text-gray-700">Created On:</dt>
                                <dd>{{ $entry->created_at->format('M d, Y h:i A') }}</dd>
                            </div>
                            @if ($entry->updated_at != $entry->created_at)
                            <div>
                                <dt class="font-medium text-gray-700">Last Updated:</dt>
                                <dd>{{ $entry->updated_at->format('M d, Y h:i A') }}</dd>
                            </div>
                            @endif
                        </dl>
                    </div>

                </div>

                <!-- Detail Card (Right Column) -->
                <div class="lg:col-span-2 bg-white rounded-xl shadow-xl border border-gray-100 p-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-3">Event Parameters</h2>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-6 text-base">
                        
                        <!-- Row 1: Scheduled/Actual -->
                        <div class="col-span-1">
                            <dt class="font-semibold text-gray-700">Scheduled Date & Time</dt>
                            <dd class="mt-1 text-gray-900 font-medium text-lg">{{ $entry->scheduled_for->format('l, M d, Y') }} @ {{ $entry->scheduled_for->format('h:i A') }}</dd>
                        </div>
                        
                        @if ($status === 'fed')
                        <div class="col-span-1">
                            <dt class="font-semibold text-green-700">Actual Fed Time</dt>
                            <dd class="mt-1 text-green-900 font-bold text-lg flex items-center">
                                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                {{ optional($entry->fed_at)->format('M d, Y') }} @ {{ optional($entry->fed_at)->format('h:i A') }}
                            </dd>
                        </div>
                        @endif

                        <!-- Row 2: Structure / Feed Type -->
                        <div class="col-span-1">
                            <dt class="font-semibold text-gray-700">Structure</dt>
                            <dd class="mt-1 text-gray-900">
                                {{ optional($entry->structure)->name ?? 'N/A' }} 
                                @if (optional($entry->group)->name)
                                    <span class="ml-2 text-sm text-indigo-600 font-medium">({{ $entry->group->name }})</span>
                                @endif
                            </dd>
                        </div>
                        <div class="col-span-1">
                            <dt class="font-semibold text-gray-700">Feed Type</dt>
                            <dd class="mt-1 text-gray-900 font-medium">{{ optional($entry->feedType)->name ?? 'Unknown Feed' }}</dd>
                        </div>

                        <!-- Row 3: Planned / Actual Amount -->
                        <div class="col-span-1">
                            <dt class="font-semibold text-gray-700">Planned Amount</dt>
                            <dd class="mt-1 text-gray-900 font-bold text-xl">{{ number_format($entry->planned_amount, 2) }} <span class="text-base font-medium text-gray-600">{{ $entry->unit }}</span></dd>
                        </div>

                        @if ($status === 'fed')
                        <div class="col-span-1">
                            <dt class="font-semibold text-green-700">Actual Amount Fed</dt>
                            <dd class="mt-1 text-green-700 font-bold text-xl">{{ number_format($entry->actual_amount, 2) }} <span class="text-base font-medium text-green-600">{{ $entry->unit }}</span></dd>
                        </div>
                        @endif

                        <!-- Row 4: Feeder / Notes (Updated to handle both Fed and Skipped) -->
                        @if (in_array($status, ['fed', 'skipped']))
                        <div class="col-span-1">
                            <dt class="font-semibold text-gray-700">
                                {{ $status === 'fed' ? 'Fed By' : 'Skipped By' }}
                            </dt>
                            <dd class="mt-1 text-gray-900">
                                {{-- FIX: Conditionally select the correct relationship based on status --}}
                                @php
                                    $user = $entry->status === 'fed' ? optional($entry->fedBy) : optional($entry->feeder);
                                @endphp
                                
                                {{ $user->name ?? 'System/Unknown' }}
                            </dd>
                        </div>
                        @endif
                        
                        <div class="col-span-2">
                            <dt class="font-semibold text-gray-700">Notes</dt>
                            <dd class="mt-1 text-gray-800 p-3 bg-gray-50 rounded-lg border border-gray-200 whitespace-pre-wrap">
                                {{ $entry->notes ?? 'No additional notes recorded for this event.' }}
                            </dd>
                        </div>

                    </dl>
                </div>
            </div>
        </main>
        <x-copyright-minimal />
    </div>
</x-app-layout>
