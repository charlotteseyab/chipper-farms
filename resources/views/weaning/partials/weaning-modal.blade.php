<!-- Weaning Modal -->
<div id="weaningModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900" id="modalTitle">Record Weaning</h3>
                <button onclick="closeWeaningModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form id="weaningForm" method="POST">
                @csrf
                <div class="mb-4">
                    <label for="weaning_date" class="block text-sm font-medium text-gray-700 mb-2">Weaning Date</label>
                    <input type="date" id="weaning_date" name="weaning_date" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
                </div>

                <div class="mb-4">
                    <label for="weaning_weight" class="block text-sm font-medium text-gray-700 mb-2">Weaning Weight (kg) - Optional</label>
                    <input type="number" id="weaning_weight" name="weaning_weight" step="0.1" min="0" max="500"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Enter weight if available">
                </div>

                <div class="mb-4">
                    <label for="weaning_method" class="block text-sm font-medium text-gray-700 mb-2">Weaning Method</label>
                    <select id="weaning_method" name="weaning_method" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="natural">Natural - Gradual separation</option>
                        <option value="gradual">Gradual - Controlled process</option>
                        <option value="forced">Forced - Immediate separation</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="weaning_reason" class="block text-sm font-medium text-gray-700 mb-2">Weaning Reason</label>
                    <select id="weaning_reason" name="weaning_reason" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="age_appropriate">Age Appropriate - Normal weaning time</option>
                        <option value="management_decision">Management Decision - Farm planning</option>
                        <option value="health_issue">Health Issue - Piglet health concerns</option>
                        <option value="mother_issue">Mother Issue - Sow health/behavior</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="post_weaning_status" class="block text-sm font-medium text-gray-700 mb-2">Post-Weaning Status</label>
                    <select id="post_weaning_status" name="post_weaning_status" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="healthy">Healthy - Normal behavior and appetite</option>
                        <option value="monitoring">Monitoring - Requires observation</option>
                        <option value="stressed">Stressed - Signs of weaning stress</option>
                        <option value="sick">Sick - Health concerns noted</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="follow_up_date" class="block text-sm font-medium text-gray-700 mb-2">Follow-up Date - Optional</label>
                    <input type="date" id="follow_up_date" name="follow_up_date"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                </div>

                <div class="mb-6">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Notes - Optional</label>
                    <textarea id="notes" name="notes" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Any observations about the weaning process..."></textarea>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeWeaningModal()" 
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
                        Record Weaning
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
