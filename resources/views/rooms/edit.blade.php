<x-app-layout>
    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h1 class="text-xl font-semibold text-gray-900">Edit Room</h1>
                        <p class="text-sm text-gray-500 mt-1">Structure: {{ $room->structure->name }}</p>
                    </div>
                    <a href="{{ route('rooms.show', $room) }}" class="flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back to Room
                    </a>
                </div>
                
                @if ($errors->any())
                    <div class="bg-red-50 border border-red-200 text-red-700 rounded p-3 mb-4 text-sm">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session('success'))
                    <div class="bg-green-50 border border-green-200 text-green-700 rounded p-3 mb-4 text-sm">
                        {{ session('success') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('rooms.update', $room) }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" value="{{ old('name', $room->name) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                            @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
                            <input type="text" name="code" value="{{ old('code', $room->code) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                            @error('code') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-gray-500 mt-1">Must be unique within this structure</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Capacity (optional)</label>
                            <input type="number" name="capacity" min="0" value="{{ old('capacity', $room->capacity) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Leave empty for unlimited">
                            @error('capacity') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
                            <select name="type" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                                <option value="general" {{ old('type', $room->type) == 'general' ? 'selected' : '' }}>General/Breeding</option>
                                <option value="maternity" {{ old('type', $room->type) == 'maternity' ? 'selected' : '' }}>Maternity</option>
                                <option value="gestation" {{ old('type', $room->type) == 'gestation' ? 'selected' : '' }}>Gestation</option>
                                <option value="weaning" {{ old('type', $room->type) == 'weaning' ? 'selected' : '' }}>Weaning</option>
                                <option value="finisher" {{ old('type', $room->type) == 'finisher' ? 'selected' : '' }}>Finisher</option>
                                <option value="quarantine" {{ old('type', $room->type) == 'quarantine' ? 'selected' : '' }}>Quarantine</option>
                            </select>
                            @error('type') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
                            <textarea name="notes" rows="3" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Special requirements, status, or any additional information about this room">{{ old('notes', $room->notes) }}</textarea>
                            @error('notes') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-gray-200">
                        <a href="{{ route('rooms.show', $room) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</a>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition shadow-md">Update Room</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <x-copyright-minimal />
</x-app-layout>

