<x-app-layout>
    <div class="container mx-auto px-4 py-6">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-900 mb-6">📊 Weight Alert System - How It Works</h1>
            
            <!-- Simple Explanation -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">🎯 Simple 3-Step Process</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="text-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <span class="text-xl">1️⃣</span>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">Set Expected Weights</h3>
                        <p class="text-sm text-gray-600">Define weight ranges for each pig type and age</p>
                    </div>
                    
                    <div class="text-center">
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <span class="text-xl">2️⃣</span>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">Record Weekly Weights</h3>
                        <p class="text-sm text-gray-600">Enter actual weights during weekly checks</p>
                    </div>
                    
                    <div class="text-center">
                        <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <span class="text-xl">3️⃣</span>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">See Instant Alerts</h3>
                        <p class="text-sm text-gray-600">Automatic alerts show in weight tables</p>
                    </div>
                </div>
            </div>

            <!-- Example Weight Standards -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">📋 Example: Expected Weights</h2>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Pig Type</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Age</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Expected Weight</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900">Large White Male</td>
                                <td class="px-4 py-2 text-sm text-gray-900">Week 16</td>
                                <td class="px-4 py-2 text-sm text-gray-900">28-38kg (optimal: 33kg)</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900">Large White Male</td>
                                <td class="px-4 py-2 text-sm text-gray-900">Week 20</td>
                                <td class="px-4 py-2 text-sm text-gray-900">35-48kg (optimal: 42kg)</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900">Large White Male</td>
                                <td class="px-4 py-2 text-sm text-gray-900">Week 24</td>
                                <td class="px-4 py-2 text-sm text-gray-900">42-57kg (optimal: 50kg)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Example Weight Recordings -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">⚖️ Example: Weight Recordings & Alerts</h2>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Pig</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actual Weight</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Alert Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Action Needed</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900">LWM1 (Week 20)</td>
                                <td class="px-4 py-2 text-sm font-medium text-gray-900">30kg</td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-600 border border-red-500">
                                        ⬇️ Under Weight (14% under)
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-sm text-red-600">Increase feed, check health</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900">LWM2 (Week 20)</td>
                                <td class="px-4 py-2 text-sm font-medium text-gray-900">42kg</td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-600">
                                        ✅ Normal
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-sm text-green-600">Continue current feeding</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900">LWM3 (Week 20)</td>
                                <td class="px-4 py-2 text-sm font-medium text-gray-900">52kg</td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-600 border border-yellow-500">
                                        ⬆️ Over Weight (8% over)
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-sm text-orange-600">Reduce feed portions</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="bg-gray-50 rounded-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">🚀 Ready to Use</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <a href="{{ route('weight-standards.index') }}" class="block p-4 bg-white rounded-lg border border-gray-200 hover:border-blue-300 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <span class="text-2xl">📊</span>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Manage Expected Weights</h3>
                                <p class="text-sm text-gray-500">Set up weight standards for your pig types</p>
                            </div>
                        </div>
                    </a>
                    
                    <a href="{{ route('weights.create') }}" class="block p-4 bg-white rounded-lg border border-gray-200 hover:border-green-300 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <span class="text-2xl">⚖️</span>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Record Weekly Weights</h3>
                                <p class="text-sm text-gray-500">Enter pig weights and see instant alerts</p>
                            </div>
                        </div>
                    </a>
                    
                    <a href="{{ route('weights.index') }}" class="block p-4 bg-white rounded-lg border border-gray-200 hover:border-orange-300 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <span class="text-2xl">📋</span>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">View Weight Records</h3>
                                <p class="text-sm text-gray-500">See all weights with alert indicators</p>
                            </div>
                        </div>
                    </a>
                    
                    <a href="{{ route('all-stocks.index') }}" class="block p-4 bg-white rounded-lg border border-gray-200 hover:border-purple-300 transition">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <span class="text-2xl">🐷</span>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Pig Management</h3>
                                <p class="text-sm text-gray-500">See weight status for all pigs</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
