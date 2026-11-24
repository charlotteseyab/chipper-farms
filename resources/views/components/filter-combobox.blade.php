@props([
    'name',
    'options' => [],          {{-- list of strings --}}
    'value' => null,
    'label' => null,
    'placeholder' => 'Choose, or type to search',
    'submitOnPick' => true,   {{-- picking an option submits the filter form --}}
])

{{-- A dropdown for a filter that still takes free text: click to choose from
     the list, or type to narrow it; Enter filters by whatever was typed.
     For lists small enough to send with the page. --}}
<div class="relative"
     x-data="{
        q: @js((string) ($value ?? request($name))), options: @js(array_values(array_unique(array_filter(array_map('strval', $options))))), open: false,
        get shown() {
            const t = this.q.trim().toLowerCase();
            return t ? this.options.filter(o => o.toLowerCase().includes(t)) : this.options;
        },
        pick(option) {
            this.q = option; this.open = false;
            @if($submitOnPick) this.$nextTick(() => this.$root.closest('form')?.requestSubmit()); @endif
        },
     }"
     @click.outside="open = false" @keydown.escape="open = false">
    @if($label)<label for="{{ $name }}" class="block text-sm font-medium text-gray-700">{{ $label }}</label>@endif
    <div class="relative mt-1">
        <input id="{{ $name }}" type="text" name="{{ $name }}" x-model="q" @focus="open = true" @click="open = true" @input="open = true" autocomplete="off"
               role="combobox" :aria-expanded="open" aria-autocomplete="list" placeholder="{{ $placeholder }}"
               {{ $attributes->merge(['class' => 'w-full rounded-lg border-gray-300 pr-9 shadow-sm focus:border-indigo-500 focus:ring-indigo-500']) }} />
        <button type="button" @click="open = ! open" class="absolute inset-y-0 right-0 flex w-9 items-center justify-center text-gray-400 hover:text-gray-600" aria-label="Show the list">
            <svg class="h-4 w-4 transition" :class="open && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
        </button>
    </div>
    <div x-show="open" x-cloak class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg" role="listbox">
        <template x-for="option in shown" :key="option">
            <button type="button" @click="pick(option)" role="option" class="block w-full px-3 py-2 text-left text-sm hover:bg-emerald-50" x-text="option"></button>
        </template>
        <p x-show="! shown.length" class="px-3 py-2 text-xs text-gray-500" x-text="options.length ? 'Nothing in the list matches. Press Enter to filter by what you typed.' : 'Nothing to choose yet. Type to filter.'"></p>
    </div>
</div>
