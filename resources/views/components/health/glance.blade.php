@props(['cards' => []])
{{-- Kept for the health pages; the cards themselves are the shared x-glance component. --}}
<x-glance :cards="$cards" {{ $attributes }} />
