{{-- Files on a visit, examination, case or follow-up. $parent is the record; $compact shrinks it for nested lists. --}}
@php
    $files = app(\App\Services\Health\HealthAttachmentService::class);
    $type = \App\Services\Health\HealthAttachmentService::typeOf($parent);
    $attached = $parent->relationLoaded('attachments') ? $parent->attachments : $parent->attachments()->with('uploadedBy')->latest()->get();
    $canAdd = $files->canAdd(auth()->user(), $parent);
    $canRemove = auth()->user()->hasPermission('correct_health_records');
    $compact = $compact ?? false;
@endphp
@if($attached->isNotEmpty() || $canAdd)
    <div class="{{ $compact ? 'mt-2' : '' }} space-y-2 text-sm">
        @unless($compact)<h3 class="font-semibold text-slate-900">Files</h3>@endunless
        @foreach($attached as $file)
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2">
                <a href="{{ route('health.attachments.download', $file) }}" class="min-w-0 font-semibold text-emerald-800 hover:underline">
                    📎 {{ $file->title ?: $file->original_filename }}
                </a>
                <span class="text-xs text-slate-500">{{ number_format(($file->size_bytes ?? 0) / 1024, 0) }} KB · {{ $file->uploadedBy?->name }} · {{ $file->created_at->format('j M Y') }}</span>
                @if($canRemove)
                    <details class="w-full text-right">
                        <summary class="cursor-pointer text-xs font-semibold text-slate-500">Remove…</summary>
                        <form method="POST" action="{{ route('health.attachments.remove', $file) }}" class="mt-2 flex flex-wrap justify-end gap-2">
                            @csrf
                            <input name="remove_reason" required minlength="5" placeholder="Why remove it?" class="rounded-lg border-slate-300 text-sm">
                            <button class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white">Remove</button>
                        </form>
                    </details>
                @endif
            </div>
        @endforeach
        @if($canAdd)
            <form method="POST" action="{{ route('health.attachments.store', ['type' => $type, 'id' => $parent->getKey()]) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                @csrf
                <input type="file" name="file" required accept="application/pdf,image/*" class="max-w-full text-xs">
                <input name="title" maxlength="255" placeholder="What is it? (optional)" class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm">
                <button class="rounded-lg border border-emerald-700 px-3 py-1.5 text-xs font-semibold text-emerald-800">Attach</button>
            </form>
        @endif
    </div>
@endif
