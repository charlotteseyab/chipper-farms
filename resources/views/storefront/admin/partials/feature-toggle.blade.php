{{--
    One storefront feature checkbox (Phase 8).

    Posted as `features[]` carrying the feature key, so an unticked box is
    simply absent — the controller reconciles the submitted list against current
    state and writes only what actually changed.
--}}
<label class="flex items-start gap-3 text-sm rounded-xl border border-slate-200 p-3">
    {{--
        An unticked checkbox posts nothing, so on a redisplay after a validation
        failure the saved state must NOT be the fallback — that would re-tick
        boxes the administrator had just cleared. When there is old input it is
        the whole truth; only a first render falls back to the stored state.
    --}}
    <input type="checkbox" name="features[]" value="{{ $key }}" class="mt-1 h-5 w-5 rounded"
           @checked(session()->hasOldInput()
               ? in_array($key, (array) old('features', []), true)
               : $flag['enabled'])>
    <span>
        <span class="font-medium text-slate-800">{{ $flag['label'] }}</span><br>
        <span class="text-slate-500">{{ $flag['description'] }}</span>
    </span>
</label>
