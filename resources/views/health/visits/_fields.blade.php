<div class="grid gap-4 sm:grid-cols-2">
    <label class="text-sm font-medium text-slate-700">Visit date and time
        <input type="datetime-local" name="visited_at" required max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('visited_at', ($visit?->visited_at ?? now())->format('Y-m-d\TH:i')) }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
    </label>
    <label class="text-sm font-medium text-slate-700">Veterinarian
        <input name="veterinarian_name" required maxlength="255" value="{{ old('veterinarian_name', $visit?->veterinarian_name) }}" placeholder="e.g. Dr Asante" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
    </label>
    <label class="text-sm font-medium text-slate-700">Clinic (optional)
        <input name="clinic" maxlength="255" value="{{ old('clinic', $visit?->clinic) }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
    </label>
    <label class="text-sm font-medium text-slate-700">Contact (optional)
        <input name="contact" maxlength="255" value="{{ old('contact', $visit?->contact) }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
    </label>
    <label class="text-sm font-medium text-slate-700">Room or area visited
        <select name="room_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
            <option value="">Whole farm / other</option>
            @foreach($rooms as $room)<option value="{{ $room->id }}" @selected((string) old('room_id', $visit?->room_id) === (string) $room->id)>{{ $room->name }}</option>@endforeach
        </select>
    </label>
    <label class="text-sm font-medium text-slate-700">Reason for the visit
        <input name="reason" maxlength="255" value="{{ old('reason', $visit?->reason) }}" placeholder="e.g. Routine herd check" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
    </label>
</div>
<label class="block text-sm font-medium text-slate-700">General notes (the farm as a whole, not individual animals)
    <textarea name="general_notes" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">{{ old('general_notes', $visit?->general_notes) }}</textarea>
</label>
<label class="block text-sm font-medium text-slate-700">Recommendations
    <textarea name="recommendations" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">{{ old('recommendations', $visit?->recommendations) }}</textarea>
</label>
