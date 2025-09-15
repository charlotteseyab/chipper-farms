<x-app-layout>

<div class="container mx-auto py-6 max-w-5xl">
    <form method="POST" action="{{ route('feed-purchase-requests.store') }}" class="bg-white p-6 rounded shadow">
        @csrf
        <div class="mb-4">
            <label class="block font-medium mb-1">Feed Type</label>
            <input type="text" name="feed_type_id" class="w-full border rounded px-3 py-2" required value="{{ old('feed_type_id') }}">
        </div>
        <div class="mb-4">
            <label class="block font-medium mb-1">Quantity</label>
            <input type="number" name="quantity" class="w-full border rounded px-3 py-2" step="0.01" min="1" required value="{{ old('quantity') }}">
        </div>
        <div class="mb-4">
            <label class="block font-medium mb-1">Unit</label>
            <input type="text" name="unit" class="w-full border rounded px-3 py-2" value="{{ old('unit', 'kg') }}" required>
        </div>
        <div class="mb-4">
            <label class="block font-medium mb-1">Cost (GHS)</label>
            <input type="number" name="cost_ghc" class="w-full border rounded px-3 py-2" step="0.01" min="0.1" required value="{{ old('cost_ghc') }}">
        </div>
        <div class="mb-4">
            <label class="block font-medium mb-1">Needed By</label>
            <input type="date" name="needed_by" class="w-full border rounded px-3 py-2" value="{{ old('needed_by') }}">
        </div>
        <div class="mb-4">
            <label class="block font-medium mb-1">Notes</label>
            <textarea name="notes" class="w-full border rounded px-3 py-2" rows="3">{{ old('notes') }}</textarea>
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Submit Request</button>
        <a href="{{ route('feed-purchase-requests.index') }}" class="ml-4 text-gray-600 hover:underline">Cancel</a>
    </form>
    <div class="mt-6 p-4 bg-gray-50 rounded shadow">
        <h2 class="font-semibold mb-2">Projected Usage Calculator</h2>
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
    </div>
</div>
<script>
document.getElementById('calc-btn').addEventListener('click', function() {
    const type = document.getElementById('calc-feed-type').value;
    const days = document.getElementById('calc-days').value;
    if (!type || !days) return;
    fetch(`/feed-inventory/analytics?feed_type=${encodeURIComponent(type)}&days=${days}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('calc-result').innerHTML =
                `Total used: <b>${data.total_used.toFixed(2)}</b> | Avg/day: <b>${data.avg_per_day.toFixed(2)}</b> | Projected needed: <b>${data.projected_needed.toFixed(2)}</b>`;
        })
        .catch(() => {
            document.getElementById('calc-result').innerHTML = 'No data available.';
        });
});
</script>
</x-app-layout>
