<x-app-layout>
    <main class="max-w-4xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Create Weight Alert</h1>
                <p class="text-sm text-slate-600 mt-1">Record a manual alert against an existing weight record.</p>
            </div>
            <a href="{{ route('weight-alerts.index') }}" class="text-sm text-slate-600 hover:text-slate-900">Back to alerts</a>
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('weight-alerts.store') }}" class="bg-white border border-slate-200 rounded-lg p-6 space-y-5">
            @csrf

            <div class="grid md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Pig</label>
                    <select name="pig_id" required class="w-full rounded-lg border-slate-300">
                        <option value="">Select pig</option>
                        @foreach ($pigs as $pig)
                            <option value="{{ $pig->id }}" @selected(old('pig_id') == $pig->id)>
                                {{ $pig->ear_tag_number ?? $pig->code }} - {{ $pig->code }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Weight Record</label>
                    <select name="weight_record_id" required class="w-full rounded-lg border-slate-300">
                        <option value="">Select weight record</option>
                        @foreach ($pigs as $pig)
                            @foreach ($pig->weights as $weight)
                                <option value="{{ $weight->id }}" @selected(old('weight_record_id') == $weight->id)>
                                    {{ $pig->ear_tag_number ?? $pig->code }} - {{ $weight->weight }}kg on {{ optional($weight->weigh_date)->format('Y-m-d') }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Alert Type</label>
                    <select name="alert_type" required class="w-full rounded-lg border-slate-300">
                        @foreach (['under_weight' => 'Under Weight', 'over_weight' => 'Over Weight', 'normal' => 'Normal', 'no_standard' => 'No Standard'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('alert_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Severity</label>
                    <select name="severity" required class="w-full rounded-lg border-slate-300">
                        @foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('severity') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Actual Weight</label>
                    <input type="number" step="0.01" min="0" name="actual_weight" value="{{ old('actual_weight') }}" required class="w-full rounded-lg border-slate-300">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Pig Age Weeks</label>
                    <input type="number" min="0" name="pig_age_weeks" value="{{ old('pig_age_weeks') }}" required class="w-full rounded-lg border-slate-300">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Expected Min Weight</label>
                    <input type="number" step="0.01" min="0" name="expected_min_weight" value="{{ old('expected_min_weight') }}" class="w-full rounded-lg border-slate-300">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Expected Max Weight</label>
                    <input type="number" step="0.01" min="0" name="expected_max_weight" value="{{ old('expected_max_weight') }}" class="w-full rounded-lg border-slate-300">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Optimal Weight</label>
                    <input type="number" step="0.01" min="0" name="optimal_weight" value="{{ old('optimal_weight') }}" class="w-full rounded-lg border-slate-300">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Deviation Percentage</label>
                    <input type="number" step="0.01" name="deviation_percentage" value="{{ old('deviation_percentage') }}" class="w-full rounded-lg border-slate-300">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Alert Message</label>
                <textarea name="alert_message" rows="4" required class="w-full rounded-lg border-slate-300">{{ old('alert_message') }}</textarea>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('weight-alerts.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700">Cancel</a>
                <button type="submit" class="px-4 py-2 rounded-lg bg-slate-900 text-white">Create Alert</button>
            </div>
        </form>
    </main>
</x-app-layout>
