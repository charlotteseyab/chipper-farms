@props(['pig'])

@php
    $tag = $pig->currentEarTagAssignment?->tag;
    $colour = $tag?->color;
    $number = $tag?->tag_number ?? ($pig->ear_tag_number ?: null);
    $isConfirmed = $colour && $colour->normalized_name !== 'unspecified';
    $hex = $isConfirmed && preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $colour->hex_color)
        ? strtoupper($colour->hex_color)
        : '#CBD5E1';
@endphp

{{-- The animal code has its own column, so this shows only the physical tag. --}}
@if($number !== null)
    <div class="inline-flex flex-col items-start gap-1">
        <span class="inline-flex items-center gap-2 whitespace-nowrap rounded-full border py-1 pl-1.5 pr-3 text-sm leading-none"
              style="background-color: {{ $hex }}1F; border-color: {{ $hex }}66;"
              title="{{ $isConfirmed ? $colour->name.' tag '.$number : 'Tag '.$number }}">
            <span class="h-4 w-4 shrink-0 rounded-full shadow-sm ring-2 ring-white"
                  style="background-color: {{ $hex }}; box-shadow: 0 0 0 1px rgba(15, 23, 42, .15);"
                  aria-hidden="true"></span>
            <span class="font-semibold text-slate-600">{{ $isConfirmed ? $colour->name : 'Tag' }}</span>
            <span class="font-black tabular-nums text-slate-900">{{ $number }}</span>
        </span>
        @unless($isConfirmed)
            <span class="pl-1 text-[11px] font-semibold text-amber-700">Colour not confirmed</span>
        @endunless
    </div>
@else
    <span class="text-sm font-medium text-slate-400">No tag</span>
@endif
