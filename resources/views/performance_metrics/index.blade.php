<x-app-layout>

    <div class="mb-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-lg sm:rounded-lg p-6">

                <!-- Performance Level Guide Card -->
                <div class="mb-6 p-4 rounded-lg border border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Performance Levels</h3>
                    <p class="text-sm text-gray-600 mb-3">Each measure is classified into three bands based on industry targets:</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="flex items-center gap-2 p-3 rounded-lg bg-green-50 border border-green-100">
                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-green-500"></span>
                            <div>
                                <div class="text-sm font-semibold text-green-800">Excellent</div>
                                <div class="text-xs text-green-700">Performance above the target threshold.</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 p-3 rounded-lg bg-yellow-50 border border-yellow-100">
                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-yellow-500"></span>
                            <div>
                                <div class="text-sm font-semibold text-yellow-800">Average</div>
                                <div class="text-xs text-yellow-700">Acceptable, near the target range.</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 p-3 rounded-lg bg-red-50 border border-red-100">
                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-500"></span>
                            <div>
                                <div class="text-sm font-semibold text-red-800">Poor</div>
                                <div class="text-xs text-red-700">Below the acceptable threshold; needs attention.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Thresholds Table (like the provided picture) -->
                <div class="overflow-x-auto mb-8">
                    <table class="min-w-full border border-gray-200">
                        <thead>
                            <tr class="bg-green-700 text-white text-xs">
                                <th class="px-3 py-2 border border-gray-200 text-left">No.</th>
                                <th class="px-3 py-2 border border-gray-200 text-left">Performance Measure</th>
                                <th class="px-3 py-2 border border-gray-200 text-left">Unit</th>
                                <th class="px-3 py-2 border border-gray-200 text-left">Excellent</th>
                                <th class="px-3 py-2 border border-gray-200 text-left">Average</th>
                                <th class="px-3 py-2 border border-gray-200 text-left">Poor</th>
                                <th class="px-3 py-2 border border-gray-200 text-left">Current Value</th>
                                <th class="px-3 py-2 border border-gray-200 text-left">Performance Level</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            @forelse(($thresholdRows ?? []) as $i => $row)
                                <tr class="odd:bg-white even:bg-gray-50">
                                    <td class="px-3 py-2 border border-gray-200 align-top">{{ $i + 1 }}</td>
                                    <td class="px-3 py-2 border border-gray-200 font-medium align-top">{{ $row['measure'] }}</td>
                                    <td class="px-3 py-2 border border-gray-200 align-top">{{ $row['unit'] }}</td>
                                    <td class="px-3 py-2 border border-gray-200 text-green-700 align-top">{{ $row['excellent'] }}</td>
                                    <td class="px-3 py-2 border border-gray-200 text-yellow-700 align-top">{{ $row['average'] }}</td>
                                    <td class="px-3 py-2 border border-gray-200 text-red-700 align-top">{{ $row['poor'] }}</td>
                                    <td class="px-3 py-2 border border-gray-200 font-semibold align-top">{{ $row['current_value'] ?? '—' }}</td>
                                    <td class="px-3 py-2 border border-gray-200 align-top">
                                        @php($lvl = $row['current_level'] ?? null)
                                        @if($lvl)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                @if ($lvl === 'Excellent') bg-green-100 text-green-800
                                                @elseif ($lvl === 'Average') bg-yellow-100 text-yellow-800
                                                @else bg-red-100 text-red-800
                                                @endif">{{ $lvl }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-3 py-6 text-center text-gray-500">No thresholds defined yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>