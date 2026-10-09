<div class="bg-white rounded-lg shadow border border-gray-200 mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">Adult Mortality (60+ days)</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pig</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age at Mortality</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cause</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Disposal</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($deaths ?? [] as $death)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ $death->pig->code ?? 'N/A' }}</div>
                            <div class="text-sm text-gray-500">{{ $death->pig?->identityAt($death->death_date) ?? '' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            @if($death->age_at_death)
                                {{ $death->age_at_death_display }}
                            @else
                                <span class="text-gray-400">N/A</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $death->death_date->format('M d, Y') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $death->death_cause_display }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $death->disposal_method_display }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600">
                            <div class="flex items-center space-x-3">
                                <a href="{{ route('deaths.show', $death->id) }}" class="hover:text-blue-800">View</a>
                                @if(!$death->pig->sales()->exists())
                                    <a href="{{ route('pigs.sale-form', $death->pig->id) }}" class="text-green-600 hover:text-green-800 font-medium">Sell</a>
                                @else
                                    <span class="text-gray-400">Sold</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">No adult mortality records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(isset($deaths) && $deaths->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $deaths->links() }}
        </div>
    @endif
</div>

<div class="bg-white rounded-lg shadow border border-gray-200 mt-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">Piglet Mortality (Litters)</h2>
        <p class="text-sm text-gray-600 mt-1">Mortality recorded against farrowings</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sow</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Count</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cause</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($pigletDeathEvents ?? [] as $event)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ optional(optional($event->farrowing)->sow)->code ?? 'N/A' }}</div>
                            <div class="text-sm text-gray-500">{{ optional(optional($event->farrowing)->sow)->ear_tag_number ?? '' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ \Carbon\Carbon::parse($event->death_date)->format('M d, Y') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-semibold">{{ $event->count }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $event->cause ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $event->notes ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">No piglet mortality records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(isset($pigletDeathEvents) && $pigletDeathEvents->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $pigletDeathEvents->links() }}
        </div>
    @endif
</div>
