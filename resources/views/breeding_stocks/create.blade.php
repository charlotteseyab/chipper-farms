<x-app-layout>
    <main class="flex-1 pl-8 pr-4 pt-8 pb-8">
        <div class="max-w-2xl mx-auto bg-white shadow-xl rounded-lg p-8">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                    <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold mb-4 text-gray-900">Form Disabled</h2>
                <p class="text-gray-600 mb-6">This form has been disabled. Please use the unified stock registration form.</p>
                
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <p class="text-sm text-green-800">
                        <strong>New Process:</strong> All pig registration is now handled through the All Stocks form where you can select the stock type.
                    </p>
                </div>
                
                <a href="{{ route('all-stocks.create') }}" class="inline-flex items-center px-6 py-3 bg-green-600 text-white font-semibold rounded-lg shadow hover:bg-green-700 transition">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Go to Stock Registration Form
                </a>
            </div>
        </div>
    </main>
</x-app-layout>
