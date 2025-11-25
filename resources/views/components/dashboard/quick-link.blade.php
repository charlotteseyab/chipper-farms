@props([
    'href' => '#',
    'title' => '',
    'subtitle' => '',
    'iconBg' => 'bg-slate-50',
    'iconColor' => 'text-slate-600',
])

<a href="{{ $href }}" {{ $attributes->class('group rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg') }}>
    <div class="flex items-center gap-4">
        <span class="rounded-2xl p-4 transition {{ $iconBg }} {{ $iconColor }} group-hover:bg-opacity-80">
            {{ $slot }}
        </span>
        <div>
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ $subtitle }}</p>
            <p class="text-lg font-semibold text-slate-900">{{ $title }}</p>
        </div>
    </div>
</a>

