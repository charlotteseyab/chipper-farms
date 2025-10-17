<x-app-layout>
    <main class="flex-1 p-8">
        <div class="max-w-6xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-blue-700">Edit Standard Stock</h2>
                <a href="{{ route('standard-stocks.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Back</a>
            </div>

            <div class="bg-white shadow-xl rounded-lg p-6">
                <form method="POST" action="{{ route('standard-stocks.update', $pig->id) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ear Tag Number</label>
                            <input type="text" name="ear_tag_number" value="{{ old('ear_tag_number', $pig->ear_tag_number) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600" required>
                            @error('ear_tag_number') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Breed</label>
                            <input type="text" name="breed" value="{{ old('breed', $pig->breed) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600" required>
                            @error('breed') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                            <select name="gender" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600" required>
                                <option value="M" {{ old('gender', $pig->gender) === 'M' ? 'selected' : '' }}>Male</option>
                                <option value="F" {{ old('gender', $pig->gender) === 'F' ? 'selected' : '' }}>Female</option>
                            </select>
                            @error('gender') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                            <input type="date" name="date_of_birth" value="{{ old('date_of_birth', optional($pig->date_of_birth)->toDateString()) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600" required>
                            @error('date_of_birth') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Structure</label>
                            <select name="structure_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                <option value="">— None —</option>
                                @foreach($structures as $s)
                                    <option value="{{ $s->id }}" {{ old('structure_id', $selectedStructureId) == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                                @endforeach
                            </select>
                            @error('structure_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Room</label>
                            <select name="room_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                <option value="">— None —</option>
                                @foreach($rooms as $r)
                                    <option value="{{ $r->id }}" {{ old('room_id', $pig->room_id) == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                                @endforeach
                            </select>
                            @error('room_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Father</label>
                            <select name="father_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                <option value="">— Unknown —</option>
                                @foreach($fathers as $f)
                                    <option value="{{ $f->id }}" {{ old('father_id', $pig->father_id) == $f->id ? 'selected' : '' }}>{{ $f->ear_tag_number }}</option>
                                @endforeach
                            </select>
                            @error('father_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mother</label>
                            <select name="mother_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                <option value="">— Unknown —</option>
                                @foreach($mothers as $m)
                                    <option value="{{ $m->id }}" {{ old('mother_id', $pig->mother_id) == $m->id ? 'selected' : '' }}>{{ $m->ear_tag_number }}</option>
                                @endforeach
                            </select>
                            @error('mother_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea name="notes" rows="3" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">{{ old('notes', $pig->notes) }}</textarea>
                            @error('notes') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('standard-stocks.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancel</a>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <xopyright-minimal />
</x-app-layout>

