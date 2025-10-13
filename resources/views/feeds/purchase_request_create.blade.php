<x-app-layout>
    <div class="max-w-6xl mx-auto py-2 px-4 sm:px-6 lg:px-8">

        <!-- <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 leading-tight">
                Create New Purchase Request 📝
            </h1>
            <p class="text-gray-500 mt-1">
                Submit a request for any item needed on the farm, including feed, medication, or equipment.
            </p>
        </div> -->

        <form method="POST" action="{{ route('feed-purchase-requests.store') }}" 
              class="bg-white p-8 rounded-xl shadow-2xl border border-gray-100 space-y-8">
            @csrf
            
            <div class="space-y-6">
                <h2 class="text-xl font-bold text-gray-800 pb-2 border-b">Item Identification</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select name="category" id="category" 
                                class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" required>
                            <option value="" disabled selected>-- Select Category --</option>
                            @php($categories = $categories ?? [ 'feed','medication','equipment','supplies','maintenance'])
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}" {{ old('category') === $cat ? 'selected' : '' }}>{{ ucfirst($cat) }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label for="item_name" class="block text-sm font-medium text-gray-700 mb-1">Item Name</label>
                        <input type="text" id="item_name" name="item_name" 
                               class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" 
                               value="{{ old('item_name') }}" 
                               placeholder="e.g., Starter Crumbles / Syringes">
                    </div>
                </div>

                <div id="feed-only-fields" class="max-w-md" style="display:none;">
                    <label for="feed_type" class="block text-sm font-medium text-gray-700 mb-1">Feed Type (Optional Legacy Name)</label>
                    <input type="text" id="feed_type" name="feed_type" 
                           class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" 
                           value="{{ old('feed_type') }}" 
                           placeholder="e.g., Grower Mash">
                    <p class="text-xs text-gray-500 mt-1">If **Category** is **Feed** and **Item Name** is left blank, this name will be used.</p>
                </div>
            </div>

            <div class="pt-6 border-t border-gray-200 space-y-6">
                <h2 class="text-xl font-bold text-gray-800 pb-2 border-b">Quantity and Estimated Cost</h2>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    
                    <div>
                        <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                        <input type="number" id="quantity" name="quantity" 
                               class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" 
                               step="0.01" min="1" required value="{{ old('quantity') }}">
                    </div>
                    
                    <div>
                        <label for="unit" class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                        <input type="text" id="unit" name="unit" 
                               class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" 
                               value="{{ old('unit', 'kg') }}" placeholder="e.g., kg, pieces, liters">
                    </div>
                    
                    <div>
                        <label for="cost_ghc" class="block text-sm font-medium text-gray-700 mb-1">Estimated Cost (₵)</label>
                        <input type="number" id="cost_ghc" name="cost_ghc" 
                               class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" 
                               step="0.01" min="0.1" required value="{{ old('cost_ghc') }}">
                    </div>
                </div>
            </div>

            <div class="pt-6 border-t border-gray-200 space-y-6">
                <h2 class="text-xl font-bold text-gray-800 pb-2 border-b">Timeline and Priority</h2>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    
                    <div>
                        <label for="needed_by" class="block text-sm font-medium text-gray-700 mb-1">Needed By Date</label>
                        <input type="date" id="needed_by" name="needed_by" 
                               class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" 
                               value="{{ old('needed_by') }}">
                    </div>
                    
                    <div>
                        <label for="vendor" class="block text-sm font-medium text-gray-700 mb-1">Preferred Vendor (Optional)</label>
                        <input type="text" id="vendor" name="vendor" 
                               class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" 
                               value="{{ old('vendor') }}">
                    </div>
                    
                    <div>
                        <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Priority (Optional)</label>
                        <select name="priority" id="priority" 
                                class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2">
                            <option value="" disabled selected>-- Select Priority --</option>
                            @foreach(['low','normal','high','urgent'] as $p)
                                <option value="{{ $p }}" {{ old('priority') === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="pt-6 border-t border-gray-200">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes / Specifications</label>
                <textarea id="notes" name="notes" 
                          class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" 
                          rows="4" placeholder="Add any specific details, brand requirements, or justification here.">{{ old('notes') }}</textarea>
            </div>

            <div class="pt-6 border-t border-gray-200 flex justify-end space-x-3">
                <a href="{{ route('feed-purchase-requests.index') }}" 
                   class="inline-flex items-center px-5 py-2 border border-gray-300 text-sm font-medium rounded-full text-gray-700 bg-white hover:bg-gray-50 transition shadow-sm">
                   Cancel
                </a>
                <button type="submit" 
                        class="inline-flex items-center px-5 py-2 border border-transparent text-sm font-medium rounded-full shadow-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                    <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-3-3v6m-4 5h8a2 2 0 002-2V7a2 2 0 00-2-2H9a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    Submit Request
                </button>
            </div>
        </form>

        <div class="mt-8 p-6 bg-indigo-50 border-2 border-indigo-200 rounded-xl shadow-lg space-y-3 hidden" id="feed-calc-card">
            <h2 class="text-lg font-bold text-indigo-800 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                Projected Feed Usage Calculator
            </h2>
            <p class="text-sm text-indigo-700">Use this to estimate the quantity needed before submitting your feed request.</p>
            <form id="usage-calc-form" class="flex flex-col md:flex-row md:items-end gap-3 pt-2 border-t border-indigo-100">
                
                <div class="flex-1">
                    <label class="block text-xs font-medium text-indigo-700">Feed Type (e.g., Starter Crumbles)</label>
                    <input type="text" id="calc-feed-type" class="w-full border-indigo-300 rounded-lg px-3 py-1.5 text-sm" value="{{ old('feed_type') }}">
                </div>
                
                <div class="w-32">
                    <label class="block text-xs font-medium text-indigo-700">Days to Project</label>
                    <input type="number" id="calc-days" class="w-full border-indigo-300 rounded-lg px-3 py-1.5 text-sm" value="7" min="1">
                </div>
                
                <button type="button" id="calc-btn" class="inline-flex items-center justify-center px-4 py-1.5 bg-indigo-600 text-white rounded-lg shadow hover:bg-indigo-700 transition h-10">
                    Calculate
                </button>
            </form>
            <div id="calc-result" class="mt-4 p-3 bg-indigo-100 rounded-lg text-sm text-indigo-800 font-medium border border-indigo-200">
                Enter details and press calculate to see projections.
            </div>
        </div>
    </div>
    <x-copyright-minimal />
    
    <script>
    const categoryEl = document.getElementById('category');
    const feedOnlyFields = document.getElementById('feed-only-fields');
    const calcCard = document.getElementById('feed-calc-card');

    function toggleFeedExtras() {
        if (!categoryEl) return;
        const isFeed = categoryEl.value === 'feed';
        
        // Toggle the legacy feed fields
        if (feedOnlyFields) feedOnlyFields.style.display = isFeed ? 'block' : 'none'; // Changed to 'block' for vertical layout

        // Toggle the calculator card
        if (calcCard) calcCard.style.display = isFeed ? 'block' : 'none';
    }

    if (categoryEl) {
        categoryEl.addEventListener('change', toggleFeedExtras);
        toggleFeedExtras(); // Initial check on load
    }

    document.getElementById('calc-btn')?.addEventListener('click', function() {
        const type = document.getElementById('calc-feed-type').value;
        const days = document.getElementById('calc-days').value;
        const resultEl = document.getElementById('calc-result');

        if (!type || !days) {
            resultEl.innerHTML = 'Please enter a Feed Type and Days.';
            return;
        }

        resultEl.innerHTML = 'Calculating...';

        fetch(`/feed-inventory/analytics?feed_type=${encodeURIComponent(type)}&days=${days}`)
            .then(r => {
                if (!r.ok) throw new Error('Network response was not ok');
                return r.json();
            })
            .then(data => {
                resultEl.innerHTML =
                    `Total used: <b>${Number(data.total_used || 0).toFixed(2)}</b>kg | Avg/day: <b>${Number(data.avg_per_day || 0).toFixed(2)}</b>kg | Projected needed: <b>${Number(data.projected_needed || 0).toFixed(2)}</b>kg`;
            })
            .catch(() => {
                resultEl.innerHTML = 'Error: No usage data available for this type.';
            });
    });
    </script>
</x-app-layout>