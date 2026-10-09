<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 pt-6 pb-24">
        <a href="{{ route('vet-visits.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Vet visits</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Record a Vet Visit</h1>
        <p class="text-sm text-slate-500">You are recorded as the person entering the notes; the vet is the name you type. After saving, add each animal the vet examined.</p>
        @if($errors->any())<ul role="alert" class="mt-4 list-disc list-inside rounded-xl bg-red-50 p-4 text-sm text-red-700">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
        <form method="POST" action="{{ route('vet-visits.store') }}" class="mt-5 space-y-5 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            @csrf
            @include('health.visits._fields', ['visit' => null])
            <x-health.pig-picker name="planned_pig_ids" :rooms="$rooms" label="Animals you want the vet to see (optional, not counted as examined)" />
            <div class="flex justify-end"><button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Save visit</button></div>
        </form>
    </div>
</x-app-layout>
