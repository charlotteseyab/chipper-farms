<x-app-layout>
    <div class="container mx-auto py-6 max-w-6xl">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Grouping Rules</h1>
            <a href="{{ route('grouping-rules.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">New Rule</a>
        </div>
        <div class="bg-white rounded-xl shadow p-6 border">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left p-2">Name</th>
                        <th class="text-left p-2">Conditions</th>
                        <th class="text-left p-2">Feeding Standard</th>
                        <th class="text-left p-2">Active</th>
                        <th class="text-left p-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rules as $rule)
                        <tr class="border-t">
                            <td class="p-2 font-medium">{{ $rule->name }}</td>
                            <td class="p-2"><pre class="text-xs bg-gray-50 p-2 rounded border overflow-x-auto">{{ json_encode($rule->conditions, JSON_PRETTY_PRINT) }}</pre></td>
                            <td class="p-2">{{ $rule->feedingStandard->pig_category ?? '-' }}</td>
                            <td class="p-2">{!! $rule->active ? '<span class="text-green-700">Yes</span>' : '<span class="text-gray-500">No</span>' !!}</td>
                            <td class="p-2 space-x-2">
                                <a href="{{ route('grouping-rules.edit', $rule) }}" class="text-blue-600 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('grouping-rules.destroy', $rule) }}" class="inline" onsubmit="return confirm('Delete this rule?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="p-4 text-gray-500" colspan="5">No rules yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $rules->links('pagination::tailwind') }}</div>
        </div>
    </div>
</x-app-layout>
