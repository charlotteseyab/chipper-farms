<x-app-layout>

<div class="container mx-auto py-6 max-w-5xl">
    <form method="POST" action="{{ route('feed-purchase-requests.store') }}" class="bg-white p-6 rounded shadow space-y-4">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block font-medium mb-1">Category</label>
                <select name="category" class="w-full border rounded px-3 py-2" required id="category">
                    <option value="" disabled selected>-- Select --</option>
                    @php($categories = $categories ?? [ 'Feed','medication','equipment','supplies','maintenance'])
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" {{ old('category') === $cat ? 'selected' : '' }}>{{ ucfirst($cat) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block font-medium mb-1">Item Name</label>
                <input type="text" name="item_name" class="w-full border rounded px-3 py-2" value="{{ old('item_name') }}" placeholder="e.g., Grower Mash / Syringes / Hose Clamp">
            </div>
        </div>

        <div id="feed-only-fields" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block font-medium mb-1">Feed Type (legacy/optional)</label>
                <input type="text" name="feed_type" class="w-full border rounded px-3 py-2" value="{{ old('feed_type') }}" placeholder="e.g., Grower Mash">
                <p class="text-xs text-gray-500 mt-1">If Category is Feed and Item Name is blank, this will be used as the item name.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block font-medium mb-1">Quantity</label>
                <input type="number" name="quantity" class="w-full border rounded px-3 py-2" step="0.01" min="1" required value="{{ old('quantity') }}">
            </div>
            <div>
                <label class="block font-medium mb-1">Unit</label>
                <input type="text" name="unit" class="w-full border rounded px-3 py-2" value="{{ old('unit', 'kg') }}" >
            </div>
            <div>
                <label class="block font-medium mb-1">Estimated Cost (GHS)</label>
                <input type="number" name="cost_ghc" class="w-full border rounded px-3 py-2" step="0.01" min="0.1" required value="{{ old('cost_ghc') }}">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block font-medium mb-1">Needed By</label>
                <input type="date" name="needed_by" class="w-full border rounded px-3 py-2" value="{{ old('needed_by') }}">
            </div>
            <div>
                <label class="block font-medium mb-1">Vendor (optional)</label>
                <input type="text" name="vendor" class="w-full border rounded px-3 py-2" value="{{ old('vendor') }}">
            </div>
            <div>
                <label class="block font-medium mb-1">Priority (optional)</label>
                <select name="priority" class="w-full border rounded px-3 py-2">
                    <option value="" disabled selected>-- Select --</option>
                    @foreach(['low','normal','high','urgent'] as $p)
                        <option value="{{ $p }}" {{ old('priority') === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block font-medium mb-1">Notes</label>
            <textarea name="notes" class="w-full border rounded px-3 py-2" rows="3">{{ old('notes') }}</textarea>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Submit Request</button>
            <a href="{{ route('feed-purchase-requests.index') }}" class="text-gray-600 hover:underline">Cancel</a>
        </div>
    </form>

    <!-- <div class="mt-6 p-4 bg-gray-50 rounded shadow" id="feed-calc-card">
        <h2 class="font-semibold mb-2">Projected Feed Usage Calculator</h2>
        <form id="usage-calc-form" class="flex flex-col md:flex-row md:items-end gap-2">
            <div>
                <label class="block text-xs">Feed Type</label>
                <input type="text" id="calc-feed-type" class="border rounded px-2 py-1" value="{{ old('feed_type') }}">
            </div>
            <div>
                <label class="block text-xs">Days</label>
                <input type="number" id="calc-days" class="border rounded px-2 py-1" value="7" min="1">
            </div>
            <button type="button" id="calc-btn" class="bg-purple-600 text-white px-3 py-1 rounded">Calculate</button>
        </form>
        <div id="calc-result" class="mt-2 text-sm text-gray-700"></div>
    </div> -->
</div>
<script>
const categoryEl = document.getElementById('category');
const calcCard = document.getElementById('feed-calc-card');
function toggleFeedExtras() {
    if (!categoryEl) return;
    const isFeed = categoryEl.value === 'feed';
    if (calcCard) calcCard.style.display = isFeed ? 'block' : 'none';
}
if (categoryEl) {
    categoryEl.addEventListener('change', toggleFeedExtras);
    toggleFeedExtras();
}

document.getElementById('calc-btn')?.addEventListener('click', function() {
    const type = document.getElementById('calc-feed-type').value;
    const days = document.getElementById('calc-days').value;
    if (!type || !days) return;
    fetch(`/feed-inventory/analytics?feed_type=${encodeURIComponent(type)}&days=${days}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('calc-result').innerHTML =
                `Total used: <b>${Number(data.total_used || 0).toFixed(2)}</b> | Avg/day: <b>${Number(data.avg_per_day || 0).toFixed(2)}</b> | Projected needed: <b>${Number(data.projected_needed || 0).toFixed(2)}</b>`;
        })
        .catch(() => {
            document.getElementById('calc-result').innerHTML = 'No data available.';
        });
});
</script>
</x-app-layout>
