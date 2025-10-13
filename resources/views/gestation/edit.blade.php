<x-app-layout>
    <div class="min-h-screen">
        <!-- Header -->
        <!-- <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <a href="{{ route('gestation.index') }}" class="text-gray-500 hover:text-gray-700">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </a>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Edit Gestation Record</h1>
                            <p class="text-gray-500">
                                <span class="inline-flex items-center">
                                    <svg class="w-4 h-4 text-blue-600 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                        <circle cx="9" cy="15" r="6" stroke="currentColor" stroke-width="2" fill="none"/>
                                        <path d="M15 9L21 3M21 3h-5M21 3v5" stroke="currentColor" stroke-width="2" fill="none"/>
                                    </svg>
                                    {{ $mating->male->ear_tag_number }}
                                </span>
                                <span class="mx-2 text-gray-400">×</span>
                                <span class="inline-flex items-center">
                                    <svg class="w-4 h-4 text-pink-600 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                        <circle cx="12" cy="8" r="5" stroke="currentColor" stroke-width="2" fill="none"/>
                                        <path d="M12 13v8M9 18h6" stroke="currentColor" stroke-width="2" fill="none"/>
                                    </svg>
                                    {{ $mating->female->ear_tag_number }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div> -->

        <main class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
            <form method="POST" action="{{ route('gestation.update', $mating) }}" class="space-y-8">
                @csrf
                @method('PUT')

                <!-- Mating Information -->
                <div class="bg-white rounded-lg shadow border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Mating Information</h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Male (Boar)</label>
                                <div class="p-3 bg-gray-50 rounded-lg">
                                    <span class="inline-flex items-center text-sm text-gray-900">
                                        <svg class="w-4 h-4 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                            <circle cx="9" cy="15" r="6" stroke="currentColor" stroke-width="2" fill="none"/>
                                            <path d="M15 9L21 3M21 3h-5M21 3v5" stroke="currentColor" stroke-width="2" fill="none"/>
                                        </svg>
                                        {{ $mating->male->ear_tag_number }} • {{ $mating->male->code }}
                                    </span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Female (Sow)</label>
                                <div class="p-3 bg-gray-50 rounded-lg">
                                    <span class="inline-flex items-center text-sm text-gray-900">
                                        <svg class="w-4 h-4 text-pink-600 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                            <circle cx="12" cy="8" r="5" stroke="currentColor" stroke-width="2" fill="none"/>
                                            <path d="M12 13v8M9 18h6" stroke="currentColor" stroke-width="2" fill="none"/>
                                        </svg>
                                        {{ $mating->female->ear_tag_number }} • {{ $mating->female->code }}
                                    </span>
                                </div>
                            </div>

                            <div>
                                <label for="actual_date" class="block text-sm font-medium text-gray-700 mb-2">Mating Date</label>
                                <input type="date" name="actual_date" id="actual_date" required max="{{ now()->toDateString() }}"
                                       value="{{ old('actual_date', $mating->actual_date ? $mating->actual_date->toDateString() : '') }}"
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                @error('actual_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="expected_farrowing_date" class="block text-sm font-medium text-gray-700 mb-2">Expected Farrowing Date</label>
                                <input type="date" name="expected_farrowing_date" id="expected_farrowing_date" required
                                       value="{{ old('expected_farrowing_date', $mating->expected_farrowing_date ? $mating->expected_farrowing_date->toDateString() : '') }}"
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                @error('expected_farrowing_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pregnancy Status -->
                <div class="bg-white rounded-lg shadow border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Pregnancy Status</h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="pregnancy_status" class="block text-sm font-medium text-gray-700 mb-2">Pregnancy Status</label>
                                <select name="pregnancy_status" id="pregnancy_status" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                    <option value="pending" {{ old('pregnancy_status', $mating->pregnancy_status) == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="pregnant" {{ old('pregnancy_status', $mating->pregnancy_status) == 'pregnant' ? 'selected' : '' }}>Pregnant</option>
                                    <option value="not_pregnant" {{ old('pregnancy_status', $mating->pregnancy_status) == 'not_pregnant' ? 'selected' : '' }}>Not Pregnant</option>
                                    <option value="uncertain" {{ old('pregnancy_status', $mating->pregnancy_status) == 'uncertain' ? 'selected' : '' }}>Uncertain</option>
                                    <option value="lost_pregnancy" {{ old('pregnancy_status', $mating->pregnancy_status) == 'lost_pregnancy' ? 'selected' : '' }}>Lost Pregnancy</option>
                                    <option value="aborted" {{ old('pregnancy_status', $mating->pregnancy_status) == 'aborted' ? 'selected' : '' }}>Aborted</option>
                                    <option value="farrowed" {{ old('pregnancy_status', $mating->pregnancy_status) == 'farrowed' ? 'selected' : '' }}>Farrowed</option>
                                </select>
                                @error('pregnancy_status')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="expected_piglets" class="block text-sm font-medium text-gray-700 mb-2">Expected Piglets</label>
                                <input type="number" name="expected_piglets" id="expected_piglets" min="1" max="25"
                                       value="{{ old('expected_piglets', $mating->expected_piglets) }}"
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                @error('expected_piglets')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="pregnancy_check_date" class="block text-sm font-medium text-gray-700 mb-2">Pregnancy Check Date</label>
                                <input type="date" name="pregnancy_check_date" id="pregnancy_check_date" max="{{ now()->toDateString() }}"
                                       value="{{ old('pregnancy_check_date', $mating->pregnancy_check_date ? $mating->pregnancy_check_date->toDateString() : '') }}"
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                @error('pregnancy_check_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="pregnancy_check_method" class="block text-sm font-medium text-gray-700 mb-2">Check Method</label>
                                <select name="pregnancy_check_method" id="pregnancy_check_method" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                    <option value="">Select Method</option>
                                    <option value="ultrasound" {{ old('pregnancy_check_method', $mating->pregnancy_check_method) == 'ultrasound' ? 'selected' : '' }}>Ultrasound</option>
                                    <option value="manual" {{ old('pregnancy_check_method', $mating->pregnancy_check_method) == 'manual' ? 'selected' : '' }}>Manual</option>
                                    <option value="blood_test" {{ old('pregnancy_check_method', $mating->pregnancy_check_method) == 'blood_test' ? 'selected' : '' }}>Blood Test</option>
                                    <option value="other" {{ old('pregnancy_check_method', $mating->pregnancy_check_method) == 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('pregnancy_check_method')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="pregnancy_confidence" class="block text-sm font-medium text-gray-700 mb-2">Confidence Level</label>
                                <select name="pregnancy_confidence" id="pregnancy_confidence" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                    <option value="">Select Confidence</option>
                                    <option value="high" {{ old('pregnancy_confidence', $mating->pregnancy_confidence) == 'high' ? 'selected' : '' }}>High</option>
                                    <option value="medium" {{ old('pregnancy_confidence', $mating->pregnancy_confidence) == 'medium' ? 'selected' : '' }}>Medium</option>
                                    <option value="low" {{ old('pregnancy_confidence', $mating->pregnancy_confidence) == 'low' ? 'selected' : '' }}>Low</option>
                                </select>
                                @error('pregnancy_confidence')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="pregnancy_status_date" class="block text-sm font-medium text-gray-700 mb-2">Status Update Date</label>
                                <input type="date" name="pregnancy_status_date" id="pregnancy_status_date" max="{{ now()->toDateString() }}"
                                       value="{{ old('pregnancy_status_date', $mating->pregnancy_status_date ? $mating->pregnancy_status_date->toDateString() : '') }}"
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                @error('pregnancy_status_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes and Additional Information -->
                <div class="bg-white rounded-lg shadow border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Notes and Additional Information</h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-6">
                            <div>
                                <label for="pregnancy_check_notes" class="block text-sm font-medium text-gray-700 mb-2">Pregnancy Check Notes</label>
                                <textarea name="pregnancy_check_notes" id="pregnancy_check_notes" rows="3" maxlength="1000"
                                          class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600"
                                          placeholder="Notes from pregnancy check...">{{ old('pregnancy_check_notes', $mating->pregnancy_check_notes) }}</textarea>
                                @error('pregnancy_check_notes')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="pregnancy_status_notes" class="block text-sm font-medium text-gray-700 mb-2">Status Update Notes</label>
                                <textarea name="pregnancy_status_notes" id="pregnancy_status_notes" rows="3" maxlength="1000"
                                          class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600"
                                          placeholder="Notes about status changes...">{{ old('pregnancy_status_notes', $mating->pregnancy_status_notes) }}</textarea>
                                @error('pregnancy_status_notes')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">General Notes</label>
                                <textarea name="notes" id="notes" rows="3" maxlength="1000"
                                          class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600"
                                          placeholder="General notes about this mating...">{{ old('notes', $mating->notes) }}</textarea>
                                @error('notes')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="outcome_notes" class="block text-sm font-medium text-gray-700 mb-2">Outcome Notes</label>
                                <textarea name="outcome_notes" id="outcome_notes" rows="3" maxlength="1000"
                                          class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600"
                                          placeholder="Notes about the outcome...">{{ old('outcome_notes', $mating->outcome_notes) }}</textarea>
                                @error('outcome_notes')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-4 mb-8">
                    <a href="{{ route('gestation.index') }}" 
                       class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Update Gestation Record
                    </button>
                </div>
            </form>
        </main>
    </div>
    <x-copyright-minimal />
</x-app-layout>
