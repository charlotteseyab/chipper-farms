<x-app-layout>
    <main class="flex-1 pl-8 pr-4 pt-8 pb-8">
        <div class="max-w-2xl mx-auto bg-white shadow-xl rounded-lg p-8">
            <h2 class="text-2xl font-bold mb-6 text-center text-blue-600">Add Standard Stock</h2>

            <form method="POST" action="{{ route('breeding-stocks.store') }}" class="space-y-6">
                @csrf
                <input type="hidden" name="stock_type" value="standard">
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Breed</label>
                    <select name="breed" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                        <option value="">Select Breed</option>
                        <option value="LW" {{ old('breed') == 'LW' ? 'selected' : '' }}>Large White</option>
                        <option value="DR" {{ old('breed') == 'DR' ? 'selected' : '' }}>Duroc</option>
                        <option value="MB" {{ old('breed') == 'MB' ? 'selected' : '' }}>Mixed Breed</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Gender</label>
                    <select name="gender" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                        <option value="">Select Gender</option>
                        <option value="M" {{ old('gender') == 'M' ? 'selected' : '' }}>Male</option>
                        <option value="F" {{ old('gender') == 'F' ? 'selected' : '' }}>Female</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Date of Birth</label>
                    <input type="date" name="date_of_birth" required max="{{ now()->toDateString() }}" value="{{ old('date_of_birth') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Father (optional)</label>
                    <select name="father_id" id="father_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                        <option value="">-- None --</option>
                        @foreach ($males as $male)
                            <option value="{{ $male->id }}" {{ old('father_id') == $male->id ? 'selected' : '' }}>
                                {{ $male->code }} - {{ $male->ear_tag_number }} ({{ $male->stock_type === 'breeding' ? 'Breeding' : 'Standard' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Mother (optional)</label>
                    <select name="mother_id" id="mother_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                        <option value="">-- None --</option>
                        @foreach ($females as $female)
                            <option value="{{ $female->id }}" {{ old('mother_id') == $female->id ? 'selected' : '' }}>
                                {{ $female->code }} - {{ $female->ear_tag_number }} ({{ $female->stock_type === 'breeding' ? 'Breeding' : 'Standard' }})
                            </option>
                        @endforeach
                    </select>
                </div>


                
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h3 class="text-sm font-semibold text-blue-800 mb-2">Auto-Generated Fields</h3>
                    <p class="text-sm text-blue-700">The following will be automatically generated:</p>
                    <ul class="text-sm text-blue-600 mt-1 list-disc list-inside">
                        <li><strong>Code:</strong> Unique pig identifier (e.g., PG00001)</li>
                        <li><strong>Ear Tag:</strong> Based on breed (e.g., LW 001)</li>
                        <li><strong>Age:</strong> Calculated from date of birth</li>
                    </ul>
                </div>
                
                <div class="pt-4">
                    <button type="submit" id="submit-button" class="w-full py-3 px-6 bg-blue-600 text-white font-bold rounded-lg shadow hover:bg-blue-700 transition">Create Standard Stock</button>
                </div>

                <div class="text-center">
                    <a href="{{ \App\Helpers\NavigationHelper::getContextualBackUrl('standard-stocks') }}" class="text-blue-600 hover:underline">← Back</a>
                </div>
            </form>
        </div>
    </main>


</x-app-layout>
