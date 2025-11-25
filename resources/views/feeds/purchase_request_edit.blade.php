<x-app-layout>
    <div class="max-w-6xl mx-auto py-4 px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Edit Procurement Request #{{ $request->id }}</h1>
            <a href="{{ route('feed-purchase-requests.show', $request) }}" class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-800">
                ← Back to details
            </a>
        </div>

        <form method="POST" action="{{ route('feed-purchase-requests.update', $request) }}" class="bg-white p-6 sm:p-8 rounded-xl shadow-xl border border-gray-100 space-y-8">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category" id="category" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg" required>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ old('category', $request->category) === $cat ? 'selected' : '' }}>{{ ucfirst($cat) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Item Name</label>
                    <input type="text" name="item_name" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg" value="{{ old('item_name', $request->item_name) }}" placeholder="e.g., Starter Crumbles" />
                </div>
            </div>

            <div id="feed-only-fields" class="{{ old('category', $request->category) === 'feed' ? '' : 'hidden' }}">
                <label class="block text-sm font-medium text-gray-700 mb-1">Feed Type (optional legacy name)</label>
                <input type="text" name="feed_type" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg" value="{{ old('feed_type', $request->feed_type) }}" placeholder="e.g., Grower Mash" />
                <p class="text-xs text-gray-500 mt-1">If Category is Feed and Item Name is blank, this value will be used.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                    <input type="number" name="quantity" step="0.01" min="1" required class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg" value="{{ old('quantity', $request->quantity) }}" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                    <input type="text" name="unit" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg" value="{{ old('unit', $request->unit) }}" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estimated Cost (₵)</label>
                    <input type="number" name="cost_ghc" step="0.01" min="0.1" required class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg" value="{{ old('cost_ghc', $request->cost_ghc) }}" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Needed By</label>
                    <input type="date" name="needed_by" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg" value="{{ old('needed_by', optional($request->needed_by)->format('Y-m-d')) }}" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vendor</label>
                    <input type="text" name="vendor" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg" value="{{ old('vendor', $request->vendor) }}" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                    <select name="priority" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg">
                        <option value="">-- Select Priority --</option>
                        @foreach(['low','normal','high','urgent'] as $p)
                            <option value="{{ $p }}" {{ old('priority', $request->priority) === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes / Specifications</label>
                <textarea name="notes" rows="4" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg">{{ old('notes', $request->notes) }}</textarea>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('feed-purchase-requests.show', $request) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-full text-sm text-gray-600 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center px-5 py-2 rounded-full text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 shadow">
                    Save Changes
                </button>
            </div>
        </form>
    </div>

    <script>
        const categoryEl = document.getElementById('category');
        const feedOnlyFields = document.getElementById('feed-only-fields');

        if (categoryEl) {
            categoryEl.addEventListener('change', () => {
                feedOnlyFields.classList.toggle('hidden', categoryEl.value !== 'feed');
            });
        }
    </script>
</x-app-layout>

