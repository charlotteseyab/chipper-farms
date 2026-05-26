{{--
    Reusable attachments block for finance entities.

    Required variables:
      $attachable  — the parent model (Receipt, Invoice, Refund)
      $parentType  — string: 'receipts' | 'invoices' | 'refunds'

    Permission check is per-parent-type:
      receipts → create_receipt
      invoices → create_invoice
      refunds  → create_refund
--}}

@php
    $writePermission = match($parentType) {
        'receipts' => 'create_receipt',
        'invoices' => 'create_invoice',
        'refunds'  => 'create_refund',
        default    => null,
    };
    $canWrite = $writePermission && auth()->user()?->hasPermission($writePermission);
    $attachments = \App\Models\Finance\FinanceAttachment::query()
        ->where('attachable_type', $attachable->getMorphClass())
        ->where('attachable_id', $attachable->getKey())
        ->with('uploadedBy')
        ->latest()
        ->get();
    $maxSizeMb = config('finance.attachments.max_size_mb', 20);
@endphp

<div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden mb-4">
    <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">
            Attachments
            @if($attachments->isNotEmpty())
                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-xs">{{ $attachments->count() }}</span>
            @endif
        </h3>
        @if($canWrite)
            <button type="button" onclick="document.getElementById('attach-form-{{ $attachable->getKey() }}').classList.toggle('hidden')"
                    class="text-sm text-emerald-700 hover:underline font-medium">+ Upload</button>
        @endif
    </div>

    @if($attachments->isEmpty())
        <p class="px-5 py-6 text-center text-sm text-slate-500">No files attached.</p>
    @else
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                <tr>
                    <th class="px-4 py-2 text-left">File</th>
                    <th class="px-4 py-2 text-left">Purpose</th>
                    <th class="px-4 py-2 text-right">Size</th>
                    <th class="px-4 py-2 text-left">Uploaded</th>
                    <th class="px-4 py-2 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($attachments as $a)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2">
                            <a href="{{ $a->downloadUrl() }}" target="_blank" class="text-emerald-700 hover:underline">{{ $a->original_name }}</a>
                            <div class="text-xs text-slate-400">{{ $a->mime_type }}</div>
                        </td>
                        <td class="px-4 py-2 text-xs text-slate-600">
                            {{ $a->purpose ?? '—' }}
                            @if($a->description)
                                <div class="text-xs text-slate-400">{{ \Illuminate\Support\Str::limit($a->description, 40) }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right text-xs text-slate-600">{{ $a->humanSize() }}</td>
                        <td class="px-4 py-2 text-xs text-slate-500">
                            {{ $a->uploadedBy?->name ?? '—' }}
                            <div class="text-xs text-slate-400">{{ $a->created_at?->format('Y-m-d H:i') }}</div>
                        </td>
                        <td class="px-4 py-2 text-right">
                            @if($canWrite)
                                <form method="POST" action="{{ route('finance.attachments.destroy', $a) }}" class="inline" onsubmit="return confirm('Remove this file?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:underline">Remove</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($canWrite)
        <form id="attach-form-{{ $attachable->getKey() }}"
              method="POST"
              action="{{ route('finance.attachments.store', [$parentType, $attachable->getKey()]) }}"
              enctype="multipart/form-data"
              class="hidden border-t border-slate-100 p-5 bg-slate-50">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 mb-1">File <span class="text-red-500">*</span></label>
                    <input type="file" name="file" required class="w-full text-sm">
                    <p class="text-xs text-slate-500 mt-1">PDF, JPG, PNG, WebP, HEIC. Max {{ $maxSizeMb }}MB.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Purpose</label>
                    <input type="text" name="purpose" placeholder="e.g. cheque_scan" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
            </div>
            <div class="mt-3">
                <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
                <input type="text" name="description" placeholder="Optional note about this file..." class="w-full rounded-xl border-slate-200 text-sm">
            </div>
            <button type="submit" class="mt-3 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Upload</button>
        </form>
    @endif
</div>
