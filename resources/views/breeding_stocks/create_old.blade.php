<x-app-layout>
    <main class="flex-1 pl-8 pr-4 pt-8 pb-8">
        <div class="max-w-2xl mx-auto bg-white shadow-xl rounded-lg p-8">
            <h2 class="text-2xl font-bold mb-6 text-center text-[#1C6C51]">Add Breeding Stock</h2>
            <form method="POST" action="{{ route('breeding-stocks.store') }}" class="space-y-6">
                @csrf
                <input type="hidden" name="stock_type" value="breeding">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Breed</label>
                    <select name="breed" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]">
                        <option value="LW">Large White</option>
                        <option value="DR">Duroc</option>
                        <option value="MB">Mixed Breed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Gender</label>
                    <select name="gender" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]">
                        <option value="M">Male</option>
                        <option value="F">Female</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Date of Birth</label>
                    <input type="date" name="date_of_birth" required max="{{ now()->toDateString() }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Father (optional)</label>
                    <select name="father_id" id="father_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]">
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
                    <select name="mother_id" id="mother_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]">
                        <option value="">-- None --</option>
                        @foreach ($females as $female)
                            <option value="{{ $female->id }}" {{ old('mother_id') == $female->id ? 'selected' : '' }}>
                                {{ $female->code }} - {{ $female->ear_tag_number }} ({{ $female->stock_type === 'breeding' ? 'Breeding' : 'Standard' }})
                            </option>
                        @endforeach
                    </select>
                </div>


                <div class="pt-4">
                    <button type="submit" id="submit-button" class="w-full py-3 px-6 bg-[#1C6C51] text-white font-bold rounded-lg shadow hover:bg-[#185941] transition">Create Breeding Stock</button>
                </div>
            </form>
        </div>
    </main>


</x-app-layout>