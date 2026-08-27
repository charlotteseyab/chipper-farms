<div class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-4 text-sm text-blue-800">
    <p class="font-semibold">Control every system email from here</p>
    <p class="mt-1 leading-6 text-blue-700">When a template is inactive, its email is suppressed without interrupting the action that triggered it.</p>
</div>

<div class="mt-6 space-y-8">
    @foreach($templates as $category => $categoryTemplates)
        <section>
            <div class="mb-3">
                <h4 class="text-base font-bold text-slate-900">{{ $category }}</h4>
                <p class="mt-0.5 text-xs text-slate-500">{{ $categoryTemplates->where('is_active', true)->count() }} of {{ $categoryTemplates->count() }} active</p>
            </div>

            <div class="space-y-3">
                @foreach($categoryTemplates as $template)
                    @php $definition = $templateDefinitions[$template->key]; @endphp
                    <details class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" @if($errors->has('template_'.$template->id)) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-4 sm:px-5">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-slate-900">{{ $template->name }}</span>
                                    @if(auth()->user()->isSuperuser())<span class="rounded-full bg-violet-50 px-2 py-0.5 text-[11px] font-semibold text-violet-700">{{ $template->organization?->name }}</span>@endif
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $template->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $template->is_active ? 'Active' : 'Inactive' }}</span>
                                    <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] text-blue-700">{{ ucfirst($template->audience) }}</span>
                                </div>
                                <p class="mt-1 truncate text-xs text-slate-500">{{ $template->subject }}</p>
                            </div>
                            <svg class="h-5 w-5 shrink-0 text-slate-400 transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"/></svg>
                        </summary>

                        <form method="POST" action="{{ route('settings.email.templates.update', $template) }}" class="space-y-5 border-t border-slate-100 bg-slate-50/60 p-4 sm:p-5">
                            @csrf
                            @method('PUT')

                            @error('template_'.$template->id)
                                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
                            @enderror

                            <label class="flex items-start justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4">
                                <span><span class="block text-sm font-semibold text-slate-800">Send this email</span><span class="mt-1 block text-xs leading-5 text-slate-500">Turning this off suppresses only this email.</span></span>
                                <span class="relative mt-1 inline-flex shrink-0 items-center">
                                    <input type="checkbox" name="is_active" value="1" class="peer sr-only" @checked($template->is_active)>
                                    <span class="h-6 w-11 rounded-full bg-slate-300 transition peer-checked:bg-emerald-600"></span>
                                    <span class="absolute left-1 h-4 w-4 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                                </span>
                            </label>

                            <div>
                                <label for="subject-{{ $template->id }}" class="text-sm font-semibold text-slate-700">Subject</label>
                                <input id="subject-{{ $template->id }}" name="subject" value="{{ $template->subject }}" required maxlength="255" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            </div>
                            <div>
                                <label for="body-{{ $template->id }}" class="text-sm font-semibold text-slate-700">Message</label>
                                <textarea id="body-{{ $template->id }}" name="body" rows="7" required maxlength="10000" class="mt-1 w-full rounded-xl border-slate-300 text-sm leading-6">{{ $template->body }}</textarea>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                                <div>
                                    <label for="action-{{ $template->id }}" class="text-sm font-semibold text-slate-700">Button label</label>
                                    <input id="action-{{ $template->id }}" name="action_label" value="{{ $template->action_label }}" maxlength="100" class="mt-1 w-full rounded-xl border-slate-300 text-sm" placeholder="No button">
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-700">Available variables</p>
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach($definition['variables'] as $variable)
                                            <code class="rounded-lg bg-slate-200 px-2 py-1 text-[11px] text-slate-700">@{{ {{ $variable }} }}</code>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button class="inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 sm:w-auto">Save template</button>
                            </div>
                        </form>
                    </details>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
