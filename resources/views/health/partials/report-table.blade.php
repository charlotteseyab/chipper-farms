{{-- A simple table from report rows (column name => value). $linkKey/$route link the first column. --}}
@php $hiddenKeys = ['medicine_id', 'pig_id']; @endphp
<div class="overflow-x-auto rounded-2xl border border-slate-100 bg-white shadow-sm">
    <table class="w-full min-w-[640px] text-sm">
        @if($rows->isNotEmpty())
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr>@foreach(array_keys(array_diff_key($rows->first(), array_flip($hiddenKeys))) as $h)<th class="px-3 py-2">{{ $h }}</th>@endforeach</tr></thead>
        @endif
        <tbody class="divide-y divide-slate-100">
            @forelse($rows as $row)
                <tr>
                    @foreach(array_values(array_diff_key($row, array_flip($hiddenKeys))) as $c => $value)
                        <td class="px-3 py-2 align-top">
                            @if($c === 0 && isset($linkKey, $route) && ! empty($row[$linkKey]))<a href="{{ route($route, $row[$linkKey]) }}" class="font-semibold text-emerald-800 hover:underline">{{ $value }}</a>@else{{ $value }}@endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="px-3 py-8 text-center text-slate-500">{{ $empty }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
