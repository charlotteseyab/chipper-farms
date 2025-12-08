<div id="litterWeaningModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-10 mx-auto p-6 border w-full max-w-lg shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold text-gray-800" id="litterModalTitle">
                Wean Litter
            </h3>
            <button type="button" onclick="closeLitterWeaningModal()" class="text-gray-400 hover:text-gray-700 text-2xl font-bold">&times;</button>
        </div>
        <form id="litterWeaningForm" method="POST">
            @csrf
            <!-- Dynamically injected litter info -->
            <!-- .litter-info will be injected here by JS -->

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Weaning Date <span class="text-red-500">*</span></label>
                <input type="date" name="weaning_date" required
                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                       value="{{ date('Y-m-d') }}">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                <textarea name="notes" rows="2" placeholder="Any notes regarding this weaning action..."
                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"></textarea>
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="closeLitterWeaningModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    Confirm Weaning
                </button>
            </div>
        </form>
    </div>
</div>
