<x-app-layout>

{{-- <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Breeding Stock Management') }}
    </h2>
</x-slot> --}}
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
    <div class="flex-1">
        <div class="max-w-6xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold">Breeding Stock Management</h2>
                <a href="{{ route('breeding-stocks.create') }}" class="px-4 py-2 bg-[#1C6C51] text-white rounded-lg font-semibold hover:bg-green-700">Add New</a>
            </div>
            <div class="bg-white shadow rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Animal ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ear Tag Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Breed</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date of Birth</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($breedingStocks as $stock)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $stock->animal_id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $stock->ear_tag_number }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $stock->breed }}</td>
                            <td class="px-6 py-4 whitespace-nowrap capitalize">{{ $stock->gender }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $stock->date_of_birth->format('Y-m-d') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $stock->status }}</td>
                            <td class="px-6 py-4 whitespace-nowrap flex gap-2">
                                <a href="{{ route('breeding-stocks.edit', $stock) }}" class="text-blue-600 hover:underline">Edit</a>
                                <form action="{{ route('breeding-stocks.destroy', $stock) }}" method="POST" onsubmit="return confirm('Are you sure?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </main>
</div>
</x-app-layout>