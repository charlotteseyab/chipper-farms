{{-- Shown on the per-pig forms when the pig has been sold or died (decision D3). --}}
@if(! empty($hasLeft))
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        @if(! empty($mayRecordForDeparted))
            <p class="font-semibold">This pig has left the farm</p>
            <p class="mt-1">You can add a record dated before it left. Say why it is being added now.</p>
            <label for="late_entry_reason" class="mt-3 block font-medium text-amber-900">Reason for adding this now</label>
            <input type="text" id="late_entry_reason" name="late_entry_reason" value="{{ old('late_entry_reason') }}" maxlength="2000" required
                   class="mt-1 block w-full rounded-md border-amber-300 focus:border-amber-500 focus:ring-amber-500">
            @error('late_entry_reason')<p class="mt-1 text-red-600">{{ $message }}</p>@enderror
        @else
            <p class="font-semibold">This pig has left the farm</p>
            <p class="mt-1">Only a farm manager can add records for it.</p>
        @endif
    </div>
@endif
