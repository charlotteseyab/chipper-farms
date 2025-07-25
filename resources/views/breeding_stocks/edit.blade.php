<x-app-layout>
    <div class="flex min-h-screen">
        @php $user = Auth::user(); @endphp
        @if($user && $user->isAdmin())
            <x-sidebar-admin />
        @elseif($user && $user->isManager())
            <x-sidebar-manager />
        @elseif($user && $user->isSuperuser())
            <x-sidebar-superuser />
        @endif
        <main class="flex-1 p-8 ml-64">
            <div class="max-w-2xl mx-auto py-10 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-bold mb-6">Edit Breeding Stock</h2>
                <form action="{{ route('breeding-stocks.update', $breedingStock) }}" method="POST" class="bg-white shadow rounded-lg p-8 space-y-6">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block font-semibold mb-1">Animal ID</label>
                        <input type="text" name="animal_id" class="w-full border-gray-300 rounded-lg" required value="{{ old('animal_id', $breedingStock->animal_id) }}">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Breed</label>
                        <input type="text" name="breed" class="w-full border-gray-300 rounded-lg" required value="{{ old('breed', $breedingStock->breed) }}">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Gender</label>
                        <select name="gender" class="w-full border-gray-300 rounded-lg" required>
                            <option value="">Select Gender</option>
                            <option value="male" @if(old('gender', $breedingStock->gender)=='male') selected @endif>Male</option>
                            <option value="female" @if(old('gender', $breedingStock->gender)=='female') selected @endif>Female</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="w-full border-gray-300 rounded-lg" required value="{{ old('date_of_birth', $breedingStock->date_of_birth->format('Y-m-d')) }}">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Status</label>
                        <input type="text" name="status" class="w-full border-gray-300 rounded-lg" required value="{{ old('status', $breedingStock->status) }}">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Notes</label>
                        <textarea name="notes" class="w-full border-gray-300 rounded-lg">{{ old('notes', $breedingStock->notes) }}</textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                        <a href="{{ route('breeding-stocks.index') }}" class="px-4 py-2 rounded-lg bg-gray-200 text-gray-700 font-semibold">Cancel</a>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-[#1C6C51] text-white font-semibold hover:bg-green-700">Update</button>
                    </div>
                </form>
            </div>
        </div>
        </main>
    </div>
</x-app-layout>