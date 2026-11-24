<x-app-layout>
    <main class="flex-1 p-8 bg-slate-50 min-h-screen">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Import Preview</h1>
                    <p class="text-sm text-slate-500 mt-1">{{ $preview['file_name'] ?? 'Excel file' }} was checked. Confirm is available only when all rows are valid.</p>
                </div>
                <a href="{{ route('all-stocks.import.create') }}" class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Upload Another File
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Rows</div>
                    <div class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($preview['total_rows']) }}</div>
                </div>
                <div class="rounded-xl border border-green-200 bg-white p-5 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wide text-green-600">Valid Rows</div>
                    <div class="mt-2 text-3xl font-bold text-green-800">{{ number_format($preview['valid_count']) }}</div>
                </div>
                <div class="rounded-xl border border-red-200 bg-white p-5 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wide text-red-600">Invalid Rows</div>
                    <div class="mt-2 text-3xl font-bold text-red-800">{{ number_format($preview['invalid_count']) }}</div>
                </div>
            </div>

            @if(!empty($preview['duplicate_headers']))
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    These columns appear more than once: <strong>{{ implode(', ', $preview['duplicate_headers']) }}</strong>. Keep one of each.
                </div>
            @endif

            @if(!empty($preview['missing_headers']))
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    Missing required headers: <strong>{{ implode(', ', $preview['missing_headers']) }}</strong>
                </div>
            @endif

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Row Validation</h2>
                        <p class="text-sm text-slate-500">Invalid rows must be fixed in the Excel file before import.</p>
                    </div>
                    @if(($preview['invalid_count'] ?? 0) === 0 && ($preview['valid_count'] ?? 0) > 0)
                        <form method="POST" action="{{ route('all-stocks.import.confirm') }}"
                              onsubmit="this.querySelector('button').disabled = true">
                            @csrf
                            <input type="hidden" name="preview_token" value="{{ $preview['token'] ?? '' }}">
                            <button type="submit" class="px-5 py-2.5 rounded-lg bg-green-600 text-white text-sm font-semibold hover:bg-green-700">
                                Confirm Import
                            </button>
                        </form>
                    @else
                        <button type="button" disabled class="px-5 py-2.5 rounded-lg bg-slate-200 text-slate-500 text-sm font-semibold cursor-not-allowed">
                            Confirm Import
                        </button>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    {{-- Every column of the uploaded file, as uploaded, in the file's order. --}}
                    @php
                        $columns = $preview['columns'] ?? \App\Services\Stock\Imports\StockExcelImportService::HEADERS;
                        $rows = collect($preview['valid_rows'])->map(fn ($r) => $r + ['valid' => true])
                            ->merge(collect($preview['invalid_rows'])->map(fn ($r) => $r + ['valid' => false]))
                            ->sortBy('row_number');
                    @endphp
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Row</th>
                                @foreach($columns as $column)
                                    <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $column }}</th>
                                @endforeach
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach($rows as $row)
                                <tr @class(['bg-red-50/50' => ! $row['valid']])>
                                    <td class="px-4 py-3 text-sm text-slate-600">{{ $row['row_number'] }}</td>
                                    @foreach($columns as $i => $column)
                                        @php $cell = $row['cells'][$i] ?? ''; @endphp
                                        <td @class(['whitespace-nowrap px-4 py-3 text-sm', 'font-semibold text-slate-900' => $i === 0, 'text-slate-600' => $i !== 0])>
                                            @if($cell === '')<span class="text-slate-300">—</span>@else{{ $cell }}@endif
                                        </td>
                                    @endforeach
                                    <td class="px-4 py-3">
                                        @if($row['valid'])
                                            <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">Valid</span>
                                        @else
                                            <div class="text-sm text-red-700">
                                                <div class="font-semibold">Invalid</div>
                                                <ul class="mt-1 list-disc pl-4">
                                                    @foreach($row['errors'] as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</x-app-layout>
