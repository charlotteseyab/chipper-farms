<x-app-layout>
    <div class="min-h-screen">
        <!-- Header -->
        <!-- <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <a href="{{ route('farrowing.show', $farrowing) }}" class="text-gray-500 hover:text-gray-700">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </a>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Edit Farrowing Record</h1>
                            <p class="text-gray-500">Record #{{ $farrowing->id }} • {{ $farrowing->farrowing_date ? $farrowing->farrowing_date->format('M d, Y') : 'Date not set' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div> -->

        <main class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('farrowing.update', $farrowing) }}" class="space-y-8">
                @csrf
                @method('PUT')

                <!-- Basic Information -->
                <div class="bg-white rounded-lg shadow border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Basic Information</h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="sow_id" class="block text-sm font-medium text-gray-700 mb-2">Sow</label>
                                <select name="sow_id" id="sow_id" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                    <option value="">Select Sow</option>
                                    @foreach($sows as $sow)
                                        <option value="{{ $sow->id }}" {{ old('sow_id', $farrowing->sow_id) == $sow->id ? 'selected' : '' }}>
                                            {{ $sow->ear_tag_number }} • {{ $sow->code }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('sow_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="boar_id" class="block text-sm font-medium text-gray-700 mb-2">Boar</label>
                                <select name="boar_id" id="boar_id" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                    <option value="">Select Boar</option>
                                    @foreach($boars as $boar)
                                        <option value="{{ $boar->id }}" {{ old('boar_id', $farrowing->boar_id) == $boar->id ? 'selected' : '' }}>
                                            {{ $boar->ear_tag_number }} • {{ $boar->code }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('boar_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="farrowing_date" class="block text-sm font-medium text-gray-700 mb-2">Farrowing Date</label>
                                <input type="date" name="farrowing_date" id="farrowing_date" required max="{{ now()->toDateString() }}"
                                       value="{{ old('farrowing_date', $farrowing->farrowing_date ? $farrowing->farrowing_date->toDateString() : '') }}"
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                @error('farrowing_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="farrowing_time" class="block text-sm font-medium text-gray-700 mb-2">Farrowing Time</label>
                                <input type="time" name="farrowing_time" id="farrowing_time"
                                       value="{{ old('farrowing_time', $farrowing->farrowing_time ? $farrowing->farrowing_time->format('H:i') : '') }}"
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                @error('farrowing_time')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Litter Information -->
                <div class="bg-white rounded-lg shadow border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Litter Information</h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="total_piglets_born" class="block text-sm font-medium text-gray-700 mb-2">Total Piglets Born</label>
                                <input type="number" name="total_piglets_born" id="total_piglets_born" required min="0" max="25"
                                       value="{{ old('total_piglets_born', $farrowing->total_piglets_born) }}"
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                @error('total_piglets_born')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="live_births" class="block text-sm font-medium text-gray-700 mb-2">Live Births</label>
                                <input type="number" name="live_births" id="live_births" required min="0" max="25"
                                       value="{{ old('live_births', $farrowing->live_births) }}"
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                @error('live_births')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="stillbirths" class="block text-sm font-medium text-gray-700 mb-2">Stillbirths</label>
                                <input type="number" name="stillbirths" id="stillbirths" required min="0" max="25"
                                       value="{{ old('stillbirths', $farrowing->stillbirths) }}"
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                @error('stillbirths')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="number_of_males" class="block text-sm font-medium text-gray-700 mb-2">Number of Males</label>
                                <input type="number" name="number_of_males" id="number_of_males" required min="0" max="25"
                                       value="{{ old('number_of_males', $farrowing->number_of_males) }}"
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                @error('number_of_males')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="number_of_females" class="block text-sm font-medium text-gray-700 mb-2">Number of Females</label>
                                <input type="number" name="number_of_females" id="number_of_females" required min="0" max="25"
                                       value="{{ old('number_of_females', $farrowing->number_of_females) }}"
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                @error('number_of_females')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="average_birth_weight" class="block text-sm font-medium text-gray-700 mb-2">Average Birth Weight (kg)</label>
                                <input type="number" name="average_birth_weight" id="average_birth_weight" step="0.01" min="0.1" max="5.0"
                                       value="{{ old('average_birth_weight', $farrowing->average_birth_weight) }}"
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                @error('average_birth_weight')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Farrowing Details -->
                <div class="bg-white rounded-lg shadow border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Farrowing Details</h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="farrowing_type" class="block text-sm font-medium text-gray-700 mb-2">Farrowing Type</label>
                                <select name="farrowing_type" id="farrowing_type" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                    <option value="natural" {{ old('farrowing_type', $farrowing->farrowing_type) == 'natural' ? 'selected' : '' }}>Natural</option>
                                    <option value="assisted" {{ old('farrowing_type', $farrowing->farrowing_type) == 'assisted' ? 'selected' : '' }}>Assisted</option>
                                    <option value="cesarean" {{ old('farrowing_type', $farrowing->farrowing_type) == 'cesarean' ? 'selected' : '' }}>Cesarean</option>
                                </select>
                                @error('farrowing_type')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="farrowing_difficulty" class="block text-sm font-medium text-gray-700 mb-2">Difficulty</label>
                                <select name="farrowing_difficulty" id="farrowing_difficulty" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                    <option value="easy" {{ old('farrowing_difficulty', $farrowing->farrowing_difficulty) == 'easy' ? 'selected' : '' }}>Easy</option>
                                    <option value="moderate" {{ old('farrowing_difficulty', $farrowing->farrowing_difficulty) == 'moderate' ? 'selected' : '' }}>Moderate</option>
                                    <option value="difficult" {{ old('farrowing_difficulty', $farrowing->farrowing_difficulty) == 'difficult' ? 'selected' : '' }}>Difficult</option>
                                </select>
                                @error('farrowing_difficulty')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="sow_condition" class="block text-sm font-medium text-gray-700 mb-2">Sow Condition</label>
                                <select name="sow_condition" id="sow_condition" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                    <option value="excellent" {{ old('sow_condition', $farrowing->sow_condition) == 'excellent' ? 'selected' : '' }}>Excellent</option>
                                    <option value="good" {{ old('sow_condition', $farrowing->sow_condition) == 'good' ? 'selected' : '' }}>Good</option>
                                    <option value="fair" {{ old('sow_condition', $farrowing->sow_condition) == 'fair' ? 'selected' : '' }}>Fair</option>
                                    <option value="poor" {{ old('sow_condition', $farrowing->sow_condition) == 'poor' ? 'selected' : '' }}>Poor</option>
                                </select>
                                @error('sow_condition')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="gestation_length_days" class="block text-sm font-medium text-gray-700 mb-2">Gestation Length (days)</label>
                                <input type="number" name="gestation_length_days" id="gestation_length_days" min="100" max="130"
                                       value="{{ old('gestation_length_days', $farrowing->gestation_length_days) }}"
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                @error('gestation_length_days')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="functional_teats" class="block text-sm font-medium text-gray-700 mb-2">Functional Teats</label>
                                <input type="number" name="functional_teats" id="functional_teats" min="0" max="20"
                                       value="{{ old('functional_teats', $farrowing->functional_teats) }}"
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                @error('functional_teats')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="sow_weight_after_farrowing" class="block text-sm font-medium text-gray-700 mb-2">Sow Weight After Farrowing (kg)</label>
                                <input type="number" name="sow_weight_after_farrowing" id="sow_weight_after_farrowing" step="0.1" min="50" max="500"
                                       value="{{ old('sow_weight_after_farrowing', $farrowing->sow_weight_after_farrowing) }}"
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                @error('sow_weight_after_farrowing')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-6">
                            <div class="flex items-center">
                                <input type="checkbox" name="colostrum_available" id="colostrum_available" value="1"
                                       {{ old('colostrum_available', $farrowing->colostrum_available) ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="colostrum_available" class="ml-2 block text-sm text-gray-900">
                                    Colostrum Available
                                </label>
                            </div>
                            @error('colostrum_available')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="bg-white rounded-lg shadow border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Additional Information</h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="follow_up_date" class="block text-sm font-medium text-gray-700 mb-2">Follow-up Date</label>
                                <input type="datetime-local" name="follow_up_date" id="follow_up_date"
                                       value="{{ old('follow_up_date', $farrowing->follow_up_date ? $farrowing->follow_up_date->format('Y-m-d\TH:i') : '') }}"
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                @error('follow_up_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select name="status" id="status" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                    <option value="active" {{ old('status', $farrowing->status) == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status', $farrowing->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    <option value="archived" {{ old('status', $farrowing->status) == 'archived' ? 'selected' : '' }}>Archived</option>
                                </select>
                                @error('status')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-6">
                            <label for="complications" class="block text-sm font-medium text-gray-700 mb-2">Complications</label>
                            <textarea name="complications" id="complications" rows="3" maxlength="1000"
                                      class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600"
                                      placeholder="Describe any complications during farrowing...">{{ old('complications', $farrowing->complications) }}</textarea>
                            @error('complications')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-6">
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" id="notes" rows="3" maxlength="1000"
                                      class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600"
                                      placeholder="Additional notes about the farrowing...">{{ old('notes', $farrowing->notes) }}</textarea>
                            @error('notes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-4">
                    <a href="{{ route('farrowing.show', $farrowing) }}" 
                       class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Update Farrowing Record
                    </button>
                </div>
            </form>
        </main>
    </div>
</x-app-layout>
