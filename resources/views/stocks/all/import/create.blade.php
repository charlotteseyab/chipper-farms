<x-app-layout>
    <main class="flex-1 p-8 bg-slate-50 min-h-screen">
        <div class="max-w-5xl mx-auto">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Import Stock</h1>
                    <p class="text-sm text-slate-500 mt-1">Upload an Excel file, review validation results, then confirm before records are created.</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('all-stocks.import.sample') }}" class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Download Sample
                    </a>
                    <a href="{{ route('all-stocks.index') }}" class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Back to All Stock
                    </a>
                </div>
            </div>

            @if($errors->any())
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <div class="font-semibold">Please correct the import issue.</div>
                    <ul class="list-disc pl-5 mt-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <section class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h2 class="text-lg font-bold text-slate-900">Upload Excel File</h2>
                        <p class="text-sm text-slate-500">No records are created during preview.</p>
                    </div>
                    <form method="POST" action="{{ route('all-stocks.import.preview') }}" enctype="multipart/form-data" class="p-6 space-y-5">
                        @csrf
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Excel File</label>
                            <input type="file" name="stock_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required class="block w-full rounded-lg border border-slate-200 bg-white text-sm text-slate-700 file:mr-4 file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                            <p class="mt-2 text-xs text-slate-500">Maximum file size: 4MB. Use the sample file to match the expected format.</p>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-5 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">
                                Preview & Validate
                            </button>
                        </div>
                    </form>
                </section>

                <aside class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Required Columns</h3>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach(\App\Services\Stock\Imports\StockExcelImportService::HEADERS as $header)
                            <span class="px-2.5 py-1 rounded-full bg-slate-100 text-xs font-semibold text-slate-700">{{ $header }}</span>
                        @endforeach
                    </div>

                    <div class="mt-6 space-y-3 text-sm text-slate-600">
                        <p><strong class="text-slate-800">gender:</strong> M or F</p>
                        <p><strong class="text-slate-800">stock_type:</strong> breeding or standard</p>
                        <p><strong class="text-slate-800">acquisition_source:</strong> born_on_farm or purchased</p>
                        <p><strong class="text-slate-800">dates:</strong> YYYY-MM-DD</p>
                    </div>
                </aside>
            </div>
        </div>
    </main>
</x-app-layout>
