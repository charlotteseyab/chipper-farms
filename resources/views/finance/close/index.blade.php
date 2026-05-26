<x-app-layout>
    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Period Close</h1>
            <p class="text-sm text-gray-500">Close fiscal periods after running the validation checklist.</p>
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Period</th>
                        <th class="px-4 py-3 text-left">Dates</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Last close run</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($periods as $period)
                        @php $latest = ($runs[$period->id] ?? collect())->first(); @endphp
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-700">{{ $period->name ?? ('Period ' . $period->period_number) }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $period->start_date->toDateString() }} → {{ $period->end_date->toDateString() }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $badge = match($period->status) {
                                        'open'   => 'bg-emerald-50 text-emerald-700',
                                        'closed' => 'bg-amber-50 text-amber-700',
                                        'locked' => 'bg-slate-200 text-slate-700',
                                        default  => 'bg-slate-50 text-slate-500',
                                    };
                                @endphp
                                <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $badge }}">{{ ucfirst($period->status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-500">
                                @if($latest)
                                    <a href="{{ route('finance.close.run', $latest) }}" class="text-blue-600 hover:underline">
                                        Run #{{ $latest->id }} ({{ $latest->status }})
                                    </a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('finance.close.show', $period) }}" class="px-3 py-1.5 bg-slate-800 text-white rounded-lg text-xs">Review</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $periods->links() }}</div>
    </div>
</x-app-layout>
