@props(['paginator', 'label' => 'records'])
{{-- Kept for the health pages; the pagination itself is the shared x-pager component. --}}
<x-pager :paginator="$paginator" :label="$label" {{ $attributes }} />
