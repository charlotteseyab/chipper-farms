<x-app-layout>
    <div class="max-w-7xl mx-auto py-2 px-4 sm:px-6 lg:px-8">

        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-8 bg-white p-6 rounded-xl shadow-lg">
            <h1 class="text-3xl font-extrabold text-gray-900 leading-tight mb-4 sm:mb-0">
                Feed Types Catalogue 
            </h1>
            <a href="{{ route('feed-types.create') }}" 
               class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-full shadow-lg text-white bg-green-600 hover:bg-green-700 transition duration-300 ease-in-out transform hover:scale-105">
               <svg class="w-5 h-5 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
               Add New Feed Type
            </a>
        </div>

        <div class="bg-white overflow-hidden shadow-2xl rounded-xl border border-gray-100">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-1/6">
                                Name & Description
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-1/12">
                                Protein (%)
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-1/12">
                                Energy (MJ/kg)
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-1/12">
                                Fiber (%)
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-1/6">
                                Feed Items
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-1/12">
                                Unit
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-1/6">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($feedTypes as $type)
                        <tr class="hover:bg-indigo-50 transition duration-150 ease-in-out">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $type->name }}</div>
                                <div class="text-xs text-gray-500 mt-1 max-w-xs truncate">{{ $type->description ?: 'No description provided.' }}</div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $type->protein_percent ?? 'N/A' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $type->energy_mj_per_kg ?? 'N/A' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $type->fiber_percent ?? 'N/A' }}</td>

                            <td class="px-6 py-4 text-sm text-gray-700 max-w-sm">
                                @php($recipe = optional($type->recipe)->items ?? collect())
                                @if($recipe->count() > 0)
                                    <ul class="space-y-1 text-xs">
                                        @foreach($recipe->take(3) as $it)
                                            <li class="flex items-center justify-start space-x-2 text-gray-600">
                                                <span class="font-medium text-gray-800">{{ optional($it->ingredient)->name ?? 'Ingredient' }}</span>
                                                <span class="text-indigo-600 font-mono">{{ number_format($it->percentage, 2) }} kg</span>
                                            </li>
                                        @endforeach
                                        @if($recipe->count() > 3)
                                            <li class="text-xs text-gray-400 mt-1">... and {{ $recipe->count() - 3 }} more.</li>
                                        @endif
                                    </ul>
                                @else
                                    <span class="text-gray-400 italic">No recipe defined.</span>
                                @endif
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-indigo-600">{{ $type->unit }}</td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-2">
                                    <a href="{{ route('feed-types.edit', $type) }}" 
                                       class="text-indigo-600 hover:text-indigo-900 p-2 rounded-full hover:bg-indigo-100 transition duration-150 ease-in-out group"
                                       title="Edit">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </a>

                                    <form action="{{ route('feed-types.destroy', $type) }}" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete the feed type: {{ $type->name }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" 
                                                class="text-red-600 hover:text-red-900 p-2 rounded-full hover:bg-red-100 transition duration-150 ease-in-out group"
                                                title="Delete">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
</x-app-layout>