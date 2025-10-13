<x-app-layout>
    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h1 class="text-xl font-semibold text-gray-900 mb-4">Create Structure</h1>
                @if ($errors->any())
                    <div class="bg-red-50 border border-red-200 text-red-700 rounded p-3 mb-4 text-sm">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form method="POST" action="{{ route('structures.store') }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input type="text" name="name" value="{{ old('name') }}" class="w-full border-gray-300 rounded-lg" required>
                        </div>
                        <!-- <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Code</label>
                            <input type="text" name="code" value="{{ old('code') }}" class="w-full border-gray-300 rounded-lg" required>
                        </div> -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Capacity (optional)</label>
                            <input type="number" name="capacity" min="0" value="{{ old('capacity') }}" class="w-full border-gray-300 rounded-lg" placeholder="Leave empty for unlimited">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" class="w-full border-gray-300 rounded-lg" required>
                                <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                <option value="under_maintenance" {{ old('status') == 'under_maintenance' ? 'selected' : '' }}>Under Maintenance</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
                            <textarea name="description" rows="3" class="w-full border-gray-300 rounded-lg" placeholder="Describe the structure, its purpose, or any important details">{{ old('description') }}</textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('structures.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg">Cancel</a>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">Create Structure</button>
                    </div>
                </form>
            </div>
        </div>
        <x-copyright-minimal />
    </div>
</x-app-layout>


