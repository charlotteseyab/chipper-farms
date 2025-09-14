<x-app-layout>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center space-x-4">
                    <a href="{{ route('feeds.index') }}" class="text-gray-500 hover:text-gray-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Record Feed</h1>
                        <p class="text-gray-500">Add a new feeding record for a pig</p>
                    </div>
                </div>
            </div>
        </div>

        <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                <form method="POST" action="{{ route('feeds.store') }}" class="p-6 space-y-6">
                    @csrf

                    <!-- Pig Selection -->
                    <div>
                        <label for="pig_id" class="block text-sm font-medium text-gray-700 mb-2">Select Pig *</label>
                        <select name="pig_id" id="pig_id" required 
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 @error('pig_id') border-red-300 @enderror">
                            <option value="">-- Choose Pig --</option>
                            @foreach($pigs as $pig)
                                <option value="{{ $pig->id }}" {{ old('pig_id') == $pig->id ? 'selected' : '' }}>
                                    {{ $pig->ear_tag_number }} - {{ $pig->code }} ({{ $pig->breed }}, {{ $pig->current_age_in_days }} days)
                                </option>
                            @endforeach
                        </select>
                        @error('pig_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Feed Type and Amount -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="feed_type" class="block text-sm font-medium text-gray-700 mb-2">Feed Type *</label>
                            <select name="feed_type" id="feed_type" required 
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 @error('feed_type') border-red-300 @enderror">
                                <option value="">-- Select Feed Type --</option>
                                <option value="starter" {{ old('feed_type') == 'starter' ? 'selected' : '' }}>Starter Feed</option>
                                <option value="grower" {{ old('feed_type') == 'grower' ? 'selected' : '' }}>Grower Feed</option>
                                <option value="finisher" {{ old('feed_type') == 'finisher' ? 'selected' : '' }}>Finisher Feed</option>
                                <option value="breeding" {{ old('feed_type') == 'breeding' ? 'selected' : '' }}>Breeding Feed</option>
                                <option value="lactating" {{ old('feed_type') == 'lactating' ? 'selected' : '' }}>Lactating Feed</option>
                                <option value="maintenance" {{ old('feed_type') == 'maintenance' ? 'selected' : '' }}>Maintenance Feed</option>
                                <option value="supplement" {{ old('feed_type') == 'supplement' ? 'selected' : '' }}>Supplement</option>
                                <option value="medicated" {{ old('feed_type') == 'medicated' ? 'selected' : '' }}>Medicated Feed</option>
                            </select>
                            @error('feed_type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="feed_amount" class="block text-sm font-medium text-gray-700 mb-2">Feed Amount *</label>
                            <div class="flex space-x-2">
                                <input type="number" name="feed_amount" id="feed_amount" step="0.01" min="0.1" max="1000" required
                                       value="{{ old('feed_amount') }}" placeholder="2.5"
                                       class="flex-1 border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 @error('feed_amount') border-red-300 @enderror">
                                <select name="feed_unit" id="feed_unit" required 
                                        class="w-20 border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 @error('feed_unit') border-red-300 @enderror">
                                    <option value="kg" {{ old('feed_unit', 'kg') == 'kg' ? 'selected' : '' }}>kg</option>
                                    <option value="lbs" {{ old('feed_unit') == 'lbs' ? 'selected' : '' }}>lbs</option>
                                    <option value="grams" {{ old('feed_unit') == 'grams' ? 'selected' : '' }}>grams</option>
                                    <option value="pounds" {{ old('feed_unit') == 'pounds' ? 'selected' : '' }}>pounds</option>
                                </select>
                            </div>
                            @error('feed_amount')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @error('feed_unit')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Date and Time -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="feed_date" class="block text-sm font-medium text-gray-700 mb-2">Feed Date *</label>
                            <input type="date" name="feed_date" id="feed_date" required
                                   value="{{ old('feed_date', now()->format('Y-m-d')) }}"
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 @error('feed_date') border-red-300 @enderror">
                            @error('feed_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="feed_time" class="block text-sm font-medium text-gray-700 mb-2">Feed Time</label>
                            <input type="time" name="feed_time" id="feed_time"
                                   value="{{ old('feed_time') }}"
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 @error('feed_time') border-red-300 @enderror">
                            @error('feed_time')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Cost and Supplier Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="feed_cost" class="block text-sm font-medium text-gray-700 mb-2">Feed Cost</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">$</span>
                                </div>
                                <input type="number" name="feed_cost" id="feed_cost" step="0.01" min="0" max="10000"
                                       value="{{ old('feed_cost') }}" placeholder="0.00"
                                       class="pl-7 w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 @error('feed_cost') border-red-300 @enderror">
                            </div>
                            @error('feed_cost')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="supplier" class="block text-sm font-medium text-gray-700 mb-2">Supplier</label>
                            <input type="text" name="supplier" id="supplier" maxlength="255"
                                   value="{{ old('supplier') }}" placeholder="Feed supplier name"
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 @error('supplier') border-red-300 @enderror">
                            @error('supplier')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Batch Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="batch_number" class="block text-sm font-medium text-gray-700 mb-2">Batch Number</label>
                            <input type="text" name="batch_number" id="batch_number" maxlength="100"
                                   value="{{ old('batch_number') }}" placeholder="Batch or lot number"
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 @error('batch_number') border-red-300 @enderror">
                            @error('batch_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="expiry_date" class="block text-sm font-medium text-gray-700 mb-2">Expiry Date</label>
                            <input type="date" name="expiry_date" id="expiry_date"
                                   value="{{ old('expiry_date') }}"
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 @error('expiry_date') border-red-300 @enderror">
                            @error('expiry_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                        <textarea name="notes" id="notes" rows="3" maxlength="1000"
                                  class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 @error('notes') border-red-300 @enderror"
                                  placeholder="Any additional notes about this feeding...">{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end space-x-4 pt-6 border-t">
                        <a href="{{ route('feeds.index') }}" 
                           class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            Record Feed
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</x-app-layout>
