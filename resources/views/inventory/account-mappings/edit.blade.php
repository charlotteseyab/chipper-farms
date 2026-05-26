<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6">
            <a href="{{ route('inventory.account-mappings.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Back</a>
            <h1 class="text-2xl font-bold text-slate-800 mt-2">Edit Inventory Account Mapping</h1>
            <p class="text-sm text-slate-500">{{ str_replace('_', ' ', $mapping->item_kind) }} · {{ $mapping->isDefaultRow() ? 'default' : $mapping->category }}</p>
        </div>

        @include('inventory.account-mappings._form', ['mapping' => $mapping])
    </div>
</x-app-layout>
