<x-app-layout>
    <div class="max-w-5xl mx-auto p-6 space-y-5">
        <h1 class="text-2xl font-bold">Tag {{ $earTag->tag_number }} history</h1>
        <p>{{ str($earTag->status)->replace('_', ' ')->headline() }} · {{ $earTag->color?->name }}</p>
        @if(session('success'))<p class="text-green-700">{{ session('success') }}</p>@endif
        @if($errors->any())<ul class="text-red-700">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
        @forelse($earTag->assignments as $assignment)
            <section class="rounded border bg-white p-5 space-y-2">
                <h2 class="font-bold">{{ $assignment->released_at ? 'Previous holder' : 'Current holder' }} · <a class="underline" href="{{ route('pigs.show', $assignment->pig_id) }}">{{ $assignment->pig?->code }}</a></h2>
                <p>Assigned {{ $assignment->assigned_at }} · Released {{ $assignment->released_at ?? 'Still assigned' }}</p>
                <p>{{ $assignment->release_reason }} {{ $assignment->notes }}</p>
                @if(auth()->user()->isAdmin())
                    <details><summary class="cursor-pointer">Correct dates or release reason</summary>
                        <form method="POST" action="{{ route('ear-tags.correct', $assignment) }}" class="grid gap-3 mt-3">@csrf @method('PATCH')
                            <label>Assigned at <input type="datetime-local" name="assigned_at" required value="{{ $assignment->assigned_at->format('Y-m-d\TH:i:s') }}" step="1"></label>
                            @if($assignment->released_at)<label>Released at <input type="datetime-local" name="released_at" required value="{{ $assignment->released_at->format('Y-m-d\TH:i:s') }}" step="1"></label>
                            <label>Release reason <input name="release_reason" required maxlength="30" value="{{ $assignment->release_reason }}"></label>@endif
                            <label>Reason for correction <input name="correction_reason" required minlength="5" maxlength="2000" class="w-full"></label>
                            <button class="bg-emerald-700 text-white rounded p-2">Save audited correction</button>
                        </form>
                    </details>
                @endif
            </section>
        @empty<p>This tag has never been assigned.</p>@endforelse
    </div>
</x-app-layout>
