@props([
    'name' => 'notification',
    'class' => 'h-5 w-5',
])

@php
    $normalized = strtolower((string) $name);
@endphp

@switch($normalized)
    @case('user')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.8"/>
            <path d="M5 20c.55-4 3-6 7-6s6.45 2 7 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        @break

    @case('shield')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 3 19 6v5.25c0 4.45-2.8 7.75-7 9.75-4.2-2-7-5.3-7-9.75V6l7-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
            <path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @case('lock')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <rect x="5" y="10" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.8"/>
            <path d="M8.5 10V7.5a3.5 3.5 0 0 1 7 0V10M12 14v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        @break

    @case('pig')
    @case('boar')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M6.5 10.5c0-3 2.45-5.5 5.5-5.5s5.5 2.5 5.5 5.5v2.25c0 3.45-2.45 6.25-5.5 6.25s-5.5-2.8-5.5-6.25V10.5Z" stroke="currentColor" stroke-width="1.8"/>
            <path d="M6.7 9.2 4.5 7.4v3.7M17.3 9.2l2.2-1.8v3.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M9.25 13.1h5.5v2.1c0 1-1.05 1.8-2.75 1.8s-2.75-.8-2.75-1.8v-2.1Z" stroke="currentColor" stroke-width="1.6"/>
            <path d="M10 10.7h.01M14 10.7h.01M11 14.75h.01M13 14.75h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        @break

    @case('alert')
    @case('warning')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 3.5 21 19H3L12 3.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
            <path d="M12 9v4.25M12 16.75h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        @break

    @case('weight')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M8.5 7.5a3.5 3.5 0 0 1 7 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M6.75 8.5h10.5l1.5 11H5.25l1.5-11Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
            <path d="M12 12.25v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        @break

    @case('success')
    @case('ok')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @case('notification')
    @case('bell')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M18 9.75a6 6 0 0 0-12 0c0 6-2.5 6.25-2.5 7.75h17C20.5 16 18 15.75 18 9.75Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M9.5 20a2.75 2.75 0 0 0 5 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        @break

    @default
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 6.75v5.5l3.25 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M20 12a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" stroke="currentColor" stroke-width="1.8"/>
        </svg>
@endswitch
