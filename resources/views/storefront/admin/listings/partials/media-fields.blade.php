<label class="block text-sm">
    <span class="text-slate-600">Upload image</span>
    <input type="file" name="image" accept="image/*" class="mt-1 w-full text-sm">
</label>
<label class="block text-sm">
    <span class="text-slate-600">...or image URL</span>
    <input type="url" name="media_url" value="{{ old('media_url') }}" placeholder="https://..." class="mt-1 w-full rounded-xl border-slate-300 text-sm">
</label>
<label class="block text-sm">
    <span class="text-slate-600">Alt text</span>
    <input type="text" name="alt_text" value="{{ old('alt_text') }}" maxlength="200" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
</label>
