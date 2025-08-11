<x-app-layout>
    <main class="flex-1 p-8">
        <div class="max-w-6xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold">Market Ready Finishers (100kg+)</h2>
                    <p class="text-gray-600">Finishers at optimal market weight - ready for sale</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('finishers.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg font-semibold hover:bg-gray-700">All Finishers</a>
                    <a href="{{ route('finishers.export.csv') }}" class="px-4 py-2 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700">Export CSV</a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Market Ready</p>
                            <p class="text-3xl font-bold text-green-600">{{ $stats['total_market_ready'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Average Weight</p>
                            <p class="text-3xl font-bold text-blue-600">{{ number_format($stats['average_weight'] ?? 0, 1) }}kg</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-100 rounded-lg">
                            <svg class="h-8 w-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 10c-4.41 0-8-1.79-8-4V6c0-2.21 3.59-4 8-4s8 1.79 8 4v8c0 2.21-3.59 4-8 4z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Est. Total Value</p>
                            <p class="text-3xl font-bold text-yellow-600">₵{{ number_format($stats['total_value'] ?? 0, 0) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Market Ready Information -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <svg class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800">Market Ready Stage Information</h3>
                        <div class="mt-2 text-sm text-green-700">
                            <p>• <strong>Weight Range:</strong> 100kg+ (optimal market weight achieved)</p>
                            <p>• <strong>Market Value:</strong> Estimated at $8/kg live weight</p>
                            <p>• <strong>Sale Timing:</strong> Ready for immediate sale - avoid overfeeding</p>
                            <p>• <strong>Feed Strategy:</strong> Maintain weight, reduce feed costs</p>
                            <p>• <strong>Action Required:</strong> Schedule sales and arrange transport</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Market Ready Table -->
            <div class="bg-white shadow-xl rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold">Market Ready Finishers (100kg+)</h3>
                    <div class="text-sm text-gray-600">
                        Total estimated value: <span class="font-semibold text-green-600">${{ number_format($stats['total_value'] ?? 0, 0) }}</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pig Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ear tag</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight (kg)</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Est. Value</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Market Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($finishers as $pig)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="text-sm font-medium text-gray-900">{{ $pig->code }}</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $pig->ear_tag_number }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $pig->gender === 'M' ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800' }}">
                                            {{ $pig->gender === 'M' ? 'Male' : 'Female' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $pig->age_in_days }} days
                                        @if($pig->age_in_weeks > 0)
                                            <span class="text-gray-500">({{ $pig->age_in_weeks }}w)</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ $pig->current_weight ?? 'N/A' }}</div>
                                        @if($pig->current_weight >= 120)
                                            <div class="text-xs text-red-500">⚠️ Consider immediate sale</div>
                                        @elseif($pig->current_weight >= 110)
                                            <div class="text-xs text-yellow-500">⚠️ Prime market weight</div>
                                        @else
                                            <div class="text-xs text-green-500">✓ Market ready</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($pig->current_weight)
                                            <div class="text-sm font-medium text-green-600">₵{{ number_format($pig->current_weight * 8, 0) }}</div>
                                            <div class="text-xs text-gray-500">@₵8/kg</div>
                                        @else
                                            <span class="text-gray-400">Need weight</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($pig->current_weight >= 120)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                Urgent Sale
                                            </span>
                                        @elseif($pig->current_weight >= 110)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                Prime Weight
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Ready for Sale
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="relative" x-data="{ open: false }">
                                            <button @click="open = !open" class="flex items-center text-gray-600 hover:text-gray-900">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                                                </svg>
                                            </button>
                                            <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10 border">
                                                <div class="py-1">
                                                    <a href="{{ route('pigs.sale-form', $pig->id) }}" class="flex items-center px-4 py-2 text-sm text-green-600 hover:bg-green-50">
                                                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 10c-4.41 0-8-1.79-8-4V6c0-2.21 3.59-4 8-4s8 1.79 8 4v8c0 2.21-3.59 4-8 4z"/>
                                                        </svg>
                                                        Record Sale
                                                    </a>
                                                    <button onclick="openWeightModal({{ $pig->id }}, '{{ $pig->code }}')" class="flex items-center w-full px-4 py-2 text-sm text-blue-600 hover:bg-blue-50">
                                                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                                        </svg>
                                                        Update Weight
                                                    </button>
                                                    <a href="{{ route('pigs.show', $pig->id) }}" class="flex items-center px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                                                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                        </svg>
                                                        View Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                        No market ready finishers found. Market ready finishers weigh 100kg or more.
                                        <div class="mt-2">
                                            <a href="{{ route('finishers.ready') }}" class="text-blue-600 hover:text-blue-800">
                                                Check near market ready finishers (80-100kg) →
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($finishers->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200">
                        {{ $finishers->links() }}
                    </div>
                @endif
            </div>

            <!-- Quick Actions -->
            @if($finishers->count() > 0)
                <div class="mt-6 bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">Quick Actions for Market Ready Pigs</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-green-900 mb-2">Schedule Sales</h4>
                            <p class="text-sm text-green-700 mb-3">Contact buyers and schedule pickup for market ready pigs</p>
                            <button class="text-green-600 hover:text-green-800 text-sm font-medium">
                                View Sales Contacts →
                            </button>
                        </div>
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-blue-900 mb-2">Export Data</h4>
                            <p class="text-sm text-blue-700 mb-3">Generate reports for market ready inventory</p>
                            <div class="space-x-2">
                                <a href="{{ route('finishers.export.csv') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">CSV</a>
                                <a href="{{ route('finishers.export.pdf') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">PDF</a>
                            </div>
                        </div>
                        <div class="bg-yellow-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-yellow-900 mb-2">Feed Management</h4>
                            <p class="text-sm text-yellow-700 mb-3">Reduce feed costs for market ready pigs</p>
                            <button class="text-yellow-600 hover:text-yellow-800 text-sm font-medium">
                                Adjust Feed Plan →
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </main>

    <!-- Weight Update Modal -->
    <div id="weightModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-3xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Update Market Ready Weight & Sale Preparation</h3>
                <form id="weightForm" method="POST">
                    @csrf
                    <div id="weightInfo" class="bg-green-50 p-4 rounded-lg mb-4">
                        <!-- Weight info will be populated by JavaScript -->
                    </div>

                    <!-- Current Weight Section -->
                    <div class="bg-white border border-gray-200 rounded-lg p-4 mb-4">
                        <h4 class="text-md font-medium text-gray-900 mb-3">📏 Market Weight</h4>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">New Weight (kg)</label>
                                <input type="number" name="weight" step="0.1" min="100" max="300" required
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                <p class="text-xs text-gray-500 mt-1">Market ready finishers weigh 100kg or more</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Weigh Date</label>
                                <input type="date" name="weigh_date" required
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                       value="{{ date('Y-m-d') }}">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Market Category</label>
                            <select name="market_category" required class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                <option value="">Select market category...</option>
                                <option value="standard">Standard Market (90-110kg)</option>
                                <option value="premium">Premium Market (110-130kg)</option>
                                <option value="heavy">Heavy Market (130kg+)</option>
                                <option value="specialty">Specialty Market</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Market category affects pricing and buyer selection</p>
                        </div>
                    </div>

                    <!-- Weekly Weight History Section -->
                    <!-- <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <h4 class="text-md font-medium text-gray-900 mb-3">📊 Final Growth Tracking (Optional)</h4>
                        <p class="text-sm text-gray-600 mb-4">Record recent weekly weights to show final growth pattern</p>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">4 Weeks Ago (kg)</label>
                                <input type="number" name="week_minus_4_weight" step="0.1" min="0" max="300"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Optional">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">3 Weeks Ago (kg)</label>
                                <input type="number" name="week_minus_3_weight" step="0.1" min="0" max="300"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Optional">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">2 Weeks Ago (kg)</label>
                                <input type="number" name="week_minus_2_weight" step="0.1" min="0" max="300"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Optional">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Last Week (kg)</label>
                                <input type="number" name="week_minus_1_weight" step="0.1" min="0" max="300"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Optional">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Final Growth Notes</label>
                            <textarea name="weekly_notes" rows="2"
                                      class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                      placeholder="Notes about final growth phase, feed efficiency, weight gain consistency..."></textarea>
                        </div>
                    </div> -->

                    <!-- Market Preparation Section -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                        <h4 class="text-md font-medium text-gray-900 mb-3">💰 Market Preparation</h4>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Expected Sale Price (₵/kg)</label>
                                <input type="number" name="expected_price_per_kg" step="0.01" min="0" max="100"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500"
                                       placeholder="Optional">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Preferred Sale Date</label>
                                <input type="date" name="preferred_sale_date"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500"
                                       min="{{ date('Y-m-d') }}">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Buyer Interest</label>
                                <select name="buyer_interest" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
                                    <option value="">Select interest level...</option>
                                    <option value="none">No inquiries yet</option>
                                    <option value="low">Some interest</option>
                                    <option value="moderate">Moderate interest</option>
                                    <option value="high">High demand</option>
                                    <option value="sold">Already sold</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Market Notes</label>
                            <textarea name="market_notes" rows="2"
                                      class="w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500"
                                      placeholder="Notes about buyer contacts, market conditions, pricing negotiations..."></textarea>
                        </div>
                    </div>

                    <!-- Health & Quality Section -->
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                        <h4 class="text-md font-medium text-gray-900 mb-3">🏥 Final Health & Quality Assessment</h4>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Body Condition Score</label>
                                <select name="body_condition_score" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                    <option value="">Select condition...</option>
                                    <option value="1">1 - Very Thin</option>
                                    <option value="2">2 - Thin</option>
                                    <option value="3">3 - Ideal</option>
                                    <option value="4">4 - Fat</option>
                                    <option value="5">5 - Very Fat</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Meat Quality Grade</label>
                                <select name="meat_quality_grade" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                    <option value="">Select grade...</option>
                                    <option value="premium">Premium (A+)</option>
                                    <option value="excellent">Excellent (A)</option>
                                    <option value="good">Good (B+)</option>
                                    <option value="standard">Standard (B)</option>
                                    <option value="fair">Fair (C)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">General Notes</label>
                            <textarea name="notes" rows="3"
                                      class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                      placeholder="Any notes about the weighing, pig condition, market readiness, sale preparation..."></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeWeightModal()"
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            Update Weight & Market Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openWeightModal(pigId, pigCode) {
            document.getElementById('weightForm').action = `/finishers/${pigId}/update-weight`;
            document.getElementById('weightInfo').innerHTML = `
                <div class="text-sm">
                    <div class="font-medium text-gray-900 mb-2">Market Ready: ${pigCode}</div>
                    <div class="text-gray-600">Update weight for this market ready finisher (100kg+)</div>
                    <div class="text-green-600 mt-1">✓ Ready for sale - consider scheduling pickup</div>
                </div>
            `;
            document.getElementById('weightModal').classList.remove('hidden');
        }

        function closeWeightModal() {
            document.getElementById('weightModal').classList.add('hidden');
            document.getElementById('weightForm').reset();
        }

        // Close modal when clicking outside
        document.getElementById('weightModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeWeightModal();
            }
        });

        // Check for success message and refresh page if weight was updated
        document.addEventListener('DOMContentLoaded', function() {
            @if(session('success'))
                // Show success message
                const successMessage = "{{ session('success') }}";

                // Create a temporary success notification
                const notification = document.createElement('div');
                notification.className = 'fixed top-20 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
                notification.innerHTML = `
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        ${successMessage}
                    </div>
                `;
                document.body.appendChild(notification);

                // Remove notification after 3 seconds
                setTimeout(() => {
                    notification.remove();
                }, 3000);

                // Refresh the page after a short delay to show updated data
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            @endif
        });
    </script>
</x-app-layout>
