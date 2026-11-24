{{-- A table from report rows (column name => value), optionally paginated. $linkKey/$route link the first column. --}}
@php
    $hiddenKeys = ['medicine_id', 'pig_id'];
    $paginated = $rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $items = $paginated ? collect($rows->items()) : $rows;
@endphp
<div class="health-table-wrap">
    <div class="overflow-x-auto">
        <table class="health-table min-w-[640px]">
            @if($items->isNotEmpty())
                <thead><tr>@foreach(array_keys(array_diff_key($items->first(), array_flip($hiddenKeys))) as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
            @endif
            <tbody>
                @forelse($items as $row)
                    <tr>
                        @foreach(array_values(array_diff_key($row, array_flip($hiddenKeys))) as $c => $value)
                            <td>
                                @if($c === 0 && isset($linkKey, $route) && ! empty($row[$linkKey]))<a href="{{ route($route, $row[$linkKey]) }}" class="font-semibold text-emerald-800 hover:underline">{{ $value }}</a>@else{{ $value }}@endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td class="health-table-empty">{{ $empty }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($paginated)
        <div class="health-table-footer"><x-health.pagination :paginator="$rows" :label="$label ?? 'rows'" /></div>
    @endif
</div>
