<?php

namespace App\Http\Controllers;

use App\Http\Requests\Stock\StorePigRequest;
use App\Http\Requests\Stock\UpdateBreedingPigRequest;
use App\Http\Requests\Stock\UpdateStandardPigRequest;
use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\Room;
use App\Models\Structure;
use App\Services\Audit\AuditTrailService;
use App\Services\Stock\Imports\StockExcelImportService;
use App\Services\Stock\PigStockService;
use App\Services\Stock\StockValidationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stock read + CRUD + exports + ear-tag check API.
 *
 * Phase 0.5 / C1-b extraction. Writes delegate to PigStockService; reads and
 * exports stay in the controller because they're view-bound and straightforward.
 *
 * Endpoints covered:
 *   - allIndex / index (breeding) / standardIndex
 *   - allCreate / create / standardCreate
 *   - store
 *   - edit / standardEdit
 *   - update / standardUpdate
 *   - destroy
 *   - show / exportPigPdf
 *   - exportCsv / exportPdf (breeding)
 *   - allExportCsv / allExportPdf
 *   - standardExportCsv / standardExportPdf
 *   - checkEarTag (api)
 *   - updateWeaningWeight (operational, not finance)
 *   - show / exportPigPdf (single pig detail)
 */
class PigStockController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected PigStockService $stockService)
    {
        //
    }

    // ---------------------------------------------------------------- reads

    public function allIndex(Request $request)
    {
        $this->authorize('viewAny', PigManagement::class);
        $q = trim((string) $request->get('q', ''));
        $query = PigManagement::active();
        $this->applyAllStockFilters($query, $request);

        $pigmanagement = $query
            ->with([
                'recentWeightAlerts' => fn ($q) => $q->where('acknowledged', false)->orderBy('created_at', 'desc')->limit(1),
                'room.structure',
                'father', 'mother',
                'latestWeight', 'sale',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->appends($request->query());

        return view('stocks.all.index', compact('pigmanagement', 'q'));
    }

    protected function applyAllStockFilters($query, Request $request): void
    {
        $q = trim((string) $request->get('q', ''));

        if ($q !== '') {
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('code', 'like', "%{$q}%")
                    ->orWhere('ear_tag_number', 'like', "%{$q}%")
                    ->orWhere('breed', 'like', "%{$q}%")
                    ->orWhere('stock_type', 'like', "%{$q}%")
                    ->orWhere('gender', 'like', "%{$q}%")
                    ->orWhereDate('date_of_birth', $q)
                    ->orWhereHas('room', function ($roomQuery) use ($q) {
                        $roomQuery->where('name', 'like', "%{$q}%")
                            ->orWhereHas('structure', function ($structureQuery) use ($q) {
                                $structureQuery->where('name', 'like', "%{$q}%");
                            });
                    })
                    ->orWhereHas('father', function ($fatherQuery) use ($q) {
                        $fatherQuery->where('ear_tag_number', 'like', "%{$q}%")
                            ->orWhere('code', 'like', "%{$q}%");
                    })
                    ->orWhereHas('mother', function ($motherQuery) use ($q) {
                        $motherQuery->where('ear_tag_number', 'like', "%{$q}%")
                            ->orWhere('code', 'like', "%{$q}%");
                    });
            });
        }

        if ($request->filled('stock_type')) $query->where('stock_type', $request->stock_type);
        if ($request->filled('breed'))      $query->where('breed', $request->breed);
        if ($request->filled('gender'))     $query->where('gender', $request->gender);

        if ($request->filled('has_weights')) {
            if ($request->has_weights === 'yes') {
                $query->whereHas('weights');
            } elseif ($request->has_weights === 'no') {
                $query->whereDoesntHave('weights');
            }
        }

        if ($request->filled('created_from')) $query->whereDate('created_at', '>=', $request->created_from);
        if ($request->filled('created_to'))   $query->whereDate('created_at', '<=', $request->created_to);

        if ($request->filled('has_recent_weigh_date') && $request->filled('recent_weigh_days')) {
            $days = (int) $request->recent_weigh_days;
            $hasRecent = $request->has_recent_weigh_date === 'yes';
            if ($hasRecent) {
                $query->whereHas('weights', fn ($w) => $w->where('weigh_date', '>=', now()->subDays($days)));
            } else {
                $query->whereDoesntHave('weights', fn ($w) => $w->where('weigh_date', '>=', now()->subDays($days)))
                    ->where('date_of_birth', '<=', now()->subDays($days));
            }
        }

        if ($request->filled('has_recent_weight') && $request->filled('recent_weight_days')) {
            $days = (int) $request->recent_weight_days;
            $hasRecent = $request->has_recent_weight === 'yes';
            if ($hasRecent) {
                $query->whereHas('weights', fn ($w) => $w->where('created_at', '>=', now()->subDays($days)));
            } else {
                $query->whereDoesntHave('weights', fn ($w) => $w->where('created_at', '>=', now()->subDays($days)));
            }
        }
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', PigManagement::class);
        $query = PigManagement::active()->where('stock_type', 'breeding');
        $this->applyBasicFilters($query, $request);

        $pigmanagement = $query->orderBy('created_at', 'desc')->paginate(10)->appends($request->query());
        return view('breeding_stocks.index', compact('pigmanagement'));
    }

    public function standardIndex(Request $request)
    {
        $this->authorize('viewAny', PigManagement::class);
        $query = PigManagement::active()->where('stock_type', 'standard');
        $this->applyBasicFilters($query, $request);

        $pigmanagement = $query->orderBy('created_at', 'desc')->paginate(10)->appends($request->query());
        return view('stocks.standard.index', compact('pigmanagement'));
    }

    protected function applyBasicFilters($query, Request $request): void
    {
        if ($request->breed)  $query->where('breed', $request->breed);
        if ($request->gender) $query->where('gender', $request->gender);
        if ($request->age)    $query->where('age_in_days', '<=', $request->age);
    }

    // ---------------------------------------------------------------- create / edit forms

    public function create()
    {
        $this->authorize('create', PigManagement::class);
        $males   = PigManagement::active()->where('gender', 'M')->get();
        $females = PigManagement::active()->where('gender', 'F')->get();
        return view('breeding_stocks.create', compact('males', 'females'));
    }

    public function standardCreate()
    {
        $this->authorize('create', PigManagement::class);
        $males   = PigManagement::active()->where('gender', 'M')->get();
        $females = PigManagement::active()->where('gender', 'F')->get();
        return view('stocks.standard.create', compact('males', 'females'));
    }

    public function allCreate()
    {
        $this->authorize('create', PigManagement::class);
        $males      = PigManagement::active()->where('gender', 'M')->get();
        $females    = PigManagement::active()->where('gender', 'F')->get();
        $structures = Structure::orderBy('name')->where('status', 'active')->get();
        return view('stocks.all.create', compact('males', 'females', 'structures'));
    }

    public function edit($id)
    {
        $pig = PigManagement::findOrFail($id);
        $this->authorize('update', $pig);
        $males   = PigManagement::where('gender', 'M')->get();
        $females = PigManagement::where('gender', 'F')->get();
        $rooms   = Room::with('structure')->orderBy('name')->get();

        return view('breeding_stocks.edit', [
            'breedingStock' => $pig,
            'males'         => $males,
            'females'       => $females,
            'rooms'         => $rooms,
        ]);
    }

    public function standardEdit(PigManagement $pig)
    {
        $this->authorize('update', $pig);
        if ($pig->stock_type !== 'standard') {
            return redirect()->route('standard-stocks.index')->with('error', 'Only standard stock can be edited here.');
        }

        $structures          = Structure::orderBy('name')->get(['id', 'name']);
        $rooms               = Room::orderBy('name')->get(['id', 'name', 'structure_id']);
        $selectedStructureId = $pig->structure_id ?: optional($pig->room)->structure_id;
        $fathers             = PigManagement::where('gender', 'M')->orderBy('ear_tag_number')->get(['id', 'ear_tag_number']);
        $mothers             = PigManagement::where('gender', 'F')->orderBy('ear_tag_number')->get(['id', 'ear_tag_number']);

        return view('stocks.standard.edit', compact('pig', 'structures', 'rooms', 'fathers', 'mothers', 'selectedStructureId'));
    }

    // ---------------------------------------------------------------- writes

    public function store(StorePigRequest $request)
    {
        $this->authorize('create', PigManagement::class);
        try {
            $pig = $this->stockService->create($request->validated());
            app(AuditTrailService::class)->log('stock', 'created', [
                'auditable' => $pig,
                'record_label' => $pig->ear_tag_number ?: $pig->code,
                'description' => "Pig {$pig->code} was created.",
                'new_values' => $pig->only(['ear_tag_number', 'breed', 'gender', 'stock_type', 'date_of_birth', 'room_id']),
            ]);

            $route = $pig->stock_type === 'breeding' ? 'breeding-stocks.index' : 'standard-stocks.index';
            return redirect()->route($route)
                ->with('success', 'Pig registered successfully as ' . $pig->stock_type . ' stock.');
        } catch (StockValidationException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        } catch (\Exception $e) {
            \Log::error('Pig creation failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to create pig. Please try again.'])->withInput();
        }
    }

    public function update(UpdateBreedingPigRequest $request, $id)
    {
        $pig = PigManagement::findOrFail($id);
        $this->authorize('update', $pig);

        try {
            $before = $pig->only(['ear_tag_number', 'breed', 'gender', 'stock_type', 'date_of_birth', 'father_id', 'mother_id', 'room_id']);
            $pig = $this->stockService->updateBreeding($pig, $request->validated());
            app(AuditTrailService::class)->logModelChanges(
                $pig,
                'stock',
                'updated',
                $before,
                $pig->only(['ear_tag_number', 'breed', 'gender', 'stock_type', 'date_of_birth', 'father_id', 'mother_id', 'room_id']),
                [
                    'record_label' => $pig->ear_tag_number ?: $pig->code,
                    'description' => "Pig {$pig->code} was updated.",
                ]
            );

            $route = $pig->stock_type === 'breeding' ? 'breeding-stocks.index' : 'standard-stocks.index';
            $label = $pig->stock_type === 'breeding' ? 'Breeding' : 'Standard';
            return redirect()->route($route)->with('success', "{$label} stock updated successfully.");
        } catch (StockValidationException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }
    }

    public function standardUpdate(UpdateStandardPigRequest $request, PigManagement $pig)
    {
        $this->authorize('update', $pig);
        try {
            $before = $pig->only(['ear_tag_number', 'breed', 'gender', 'date_of_birth', 'father_id', 'mother_id', 'room_id', 'notes']);
            $updatedPig = $this->stockService->updateStandard($pig, $request->validated());
            app(AuditTrailService::class)->logModelChanges(
                $updatedPig,
                'stock',
                'updated',
                $before,
                $updatedPig->only(['ear_tag_number', 'breed', 'gender', 'date_of_birth', 'father_id', 'mother_id', 'room_id', 'notes']),
                [
                    'record_label' => $updatedPig->ear_tag_number ?: $updatedPig->code,
                    'description' => "Pig {$updatedPig->code} was updated.",
                ]
            );
            return redirect()->route('standard-stocks.index')->with('success', 'Standard stock updated successfully.');
        } catch (StockValidationException $e) {
            if ($e->field === 'stock_type') {
                return redirect()->route('standard-stocks.index')->with('error', $e->getMessage());
            }
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }
    }

    public function destroy($id)
    {
        $pig = PigManagement::findOrFail($id);
        $this->authorize('delete', $pig);
        $label = $pig->ear_tag_number ?: $pig->code;
        $code = $pig->code;
        $oldValues = $pig->only(['ear_tag_number', 'breed', 'gender', 'stock_type', 'room_id']);
        $pig->delete();
        app(AuditTrailService::class)->log('stock', 'archived', [
            'auditable' => $pig,
            'record_label' => $label,
            'description' => "Pig {$code} was archived.",
            'old_values' => $oldValues,
        ]);

        if (request()->headers->get('referer')) {
            return redirect()->back()->with('success', 'Pig deleted successfully.');
        }
        return redirect()->route('breeding-stocks.index')->with('success', 'Pig deleted successfully.');
    }

    // ---------------------------------------------------------------- show + single-pig pdf

    public function show($id)
    {
        $pig = PigManagement::withTrashed()->with([
            'mother', 'father',
            'weights' => fn ($q) => $q->latest('weigh_date'),
            'latestWeight',
            'maleMatings' => fn ($q) => $q->with(['female', 'createdBy'])->latest('created_at'),
            'femaleMatings' => fn ($q) => $q->with(['male', 'createdBy'])->latest('created_at'),
            'farrowingsAsSow' => fn ($q) => $q->with(['boar', 'weanings', 'pigletDeaths'])->orderBy('farrowing_date', 'desc'),
            'farrowingsAsBoar' => fn ($q) => $q->with(['sow', 'weanings', 'pigletDeaths'])->orderBy('farrowing_date', 'desc'),
            'sale',
            'deaths',
        ])->findOrFail($id);

        $this->authorize('view', $pig);

        $viewData = $this->buildShowViewData($pig);
        return view('pigs.show', $viewData);
    }

    public function exportPigPdf($id)
    {
        $pig = PigManagement::withTrashed()->with([
            'mother', 'father',
            'weights' => fn ($q) => $q->latest('weigh_date'),
            'latestWeight',
            'maleMatings' => fn ($q) => $q->with(['female', 'createdBy'])->latest('created_at'),
            'femaleMatings' => fn ($q) => $q->with(['male', 'createdBy'])->latest('created_at'),
            'farrowingsAsSow' => fn ($q) => $q->with(['boar', 'weanings', 'pigletDeaths'])->orderBy('farrowing_date', 'desc'),
            'farrowingsAsBoar' => fn ($q) => $q->with(['sow', 'weanings', 'pigletDeaths'])->orderBy('farrowing_date', 'desc'),
            'sale', 'deaths',
        ])->findOrFail($id);

        $this->authorize('view', $pig);

        $viewData = $this->buildShowViewData($pig);

        $pdf = Pdf::loadView('pigs.show_pdf', $viewData);
        $fileName = 'pig_' . ($pig->ear_tag_number ?: $pig->id) . '_' . date('Y-m-d') . '.pdf';
        return $pdf->download($fileName);
    }

    /**
     * Build the view-model used by both show() and exportPigPdf(). The
     * original methods duplicated this logic; extracted here to a single
     * private helper to eliminate drift.
     */
    protected function buildShowViewData(PigManagement $pig): array
    {
        $breedingReadiness  = $pig->breeding_readiness;
        $availabilityStatus = $pig->availability_status;

        $offspring = PigManagement::where(function ($query) use ($pig) {
            if ($pig->gender === 'M') {
                $query->where('father_id', $pig->id);
            } else {
                $query->where('mother_id', $pig->id);
            }
        })->with(['father', 'mother'])->get();

        $siblings = collect();
        if ($pig->father_id || $pig->mother_id) {
            $siblings = PigManagement::where('id', '!=', $pig->id)
                ->where(function ($query) use ($pig) {
                    if ($pig->father_id) $query->where('father_id', $pig->father_id);
                    if ($pig->mother_id) $query->orWhere('mother_id', $pig->mother_id);
                })
                ->get();
        }

        if ($pig->gender === 'F') {
            $totalOffspring = $pig->farrowingsAsSow->sum(function ($farrowing) {
                $live   = (int) ($farrowing->live_births ?? 0);
                $deaths = (int) $farrowing->pigletDeaths->sum('count');
                return max(0, $live - $deaths);
            });
        } else {
            $totalOffspring = $pig->farrowingsAsBoar->sum(function ($farrowing) {
                $live   = (int) ($farrowing->live_births ?? 0);
                $deaths = (int) $farrowing->pigletDeaths->sum('count');
                return max(0, $live - $deaths);
            });
        }

        $stats = [
            'total_matings' => $pig->maleMatings->count() + $pig->femaleMatings->count(),
            'successful_matings' => $pig->maleMatings->where('successful', true)->count()
                + $pig->femaleMatings->where('successful', true)->count(),
            'total_offspring' => $totalOffspring,
            'weight_records' => $pig->weights->count(),
            'days_since_last_weight' => $pig->latestWeight ? $pig->latestWeight->days_ago : null,
        ];

        $analytics     = [];
        $allWeightsAsc = $pig->weights()->orderBy('weigh_date', 'asc')->get();
        if ($allWeightsAsc->count() >= 2) {
            $analytics['total_records']  = $allWeightsAsc->count();
            $analytics['first_weight']   = $allWeightsAsc->first();
            $analytics['latest_weight']  = $allWeightsAsc->last();
            $analytics['weight_gain']    = $analytics['latest_weight']->weight - $analytics['first_weight']->weight;

            $daysBetween = $analytics['first_weight']->weigh_date->diffInDays($analytics['latest_weight']->weigh_date);
            $analytics['avg_daily_gain'] = $daysBetween > 0 ? $analytics['weight_gain'] / $daysBetween : 0;

            $analytics['growth_periods'] = [];
            for ($i = 1; $i < $allWeightsAsc->count(); $i++) {
                $current  = $allWeightsAsc->values()[$i];
                $previous = $allWeightsAsc->values()[$i - 1];
                $days     = $previous->weigh_date->diffInDays($current->weigh_date);
                $gain     = $current->weight - $previous->weight;

                $analytics['growth_periods'][] = [
                    'from_date'   => $previous->weigh_date,
                    'to_date'     => $current->weigh_date,
                    'days'        => $days,
                    'weight_gain' => $gain,
                    'daily_gain'  => $days > 0 ? $gain / $days : 0,
                    'from_weight' => $previous->weight,
                    'to_weight'   => $current->weight,
                ];
            }
        }

        return compact('pig', 'breedingReadiness', 'availabilityStatus', 'offspring', 'siblings', 'stats', 'analytics');
    }

    // ---------------------------------------------------------------- exports

    public function exportCsv(Request $request)
    {
        $this->authorize('viewAny', PigManagement::class);
        $query = PigManagement::active()->where('stock_type', 'breeding')->with(['latestWeight', 'sale']);
        $this->applyBasicFilters($query, $request);

        return $this->streamCsv('breeding_stocks_' . date('Y-m-d') . '.csv', $query->get(), [
            'Code', 'Ear Tag', 'Breed', 'Gender', 'Date of Birth', 'Age (days)',
            'Breeding Ready', 'Age Status', 'Weight Status', 'Current Weight',
            'Latest Weight Date', 'Father', 'Mother',
        ], function ($pig) {
            $r = $pig->breeding_readiness;
            return [
                $pig->code,
                $pig->ear_tag_number,
                $pig->breed,
                $pig->gender === 'M' ? 'Male' : 'Female',
                $pig->date_of_birth ? $pig->date_of_birth->format('Y-m-d') : 'Not set',
                $pig->current_age_in_days,
                $r['ready'] ? 'Yes' : 'No',
                ucfirst(str_replace('_', ' ', $r['age_status'])),
                ucfirst(str_replace('_', ' ', $r['factors']['weight']['status'])),
                $pig->latestWeight ? $pig->latestWeight->weight . ' kg' : 'No data',
                $pig->latestWeight && $pig->latestWeight->weigh_date ? $pig->latestWeight->weigh_date->format('Y-m-d') : 'No data',
                $pig->father ? $pig->father->ear_tag_number : 'N/A',
                $pig->mother ? $pig->mother->ear_tag_number : 'N/A',
            ];
        });
    }

    public function exportPdf(Request $request)
    {
        $this->authorize('viewAny', PigManagement::class);
        $query = PigManagement::active()->where('stock_type', 'breeding')->with(['latestWeight', 'sale', 'father', 'mother']);
        $this->applyBasicFilters($query, $request);
        $pigs = $query->get();
        $pdf = Pdf::loadView('breeding_stocks.pdf', compact('pigs'));
        return $pdf->download('breeding_stocks_' . date('Y-m-d') . '.pdf');
    }

    public function allExportCsv(Request $request)
    {
        return $this->allExportExcel($request, app(StockExcelImportService::class));
    }

    public function allExportExcel(Request $request, StockExcelImportService $service)
    {
        $this->authorize('viewAny', PigManagement::class);
        $query = PigManagement::active()->with(['latestWeight', 'sale', 'father', 'mother', 'room.structure']);
        $this->applyAllStockFilters($query, $request);

        $rows = (function () use ($query) {
            yield [
            'code', 'ear_tag_number', 'breed', 'gender', 'stock_type', 'date_of_birth', 'age',
            'Structure', 'room_name', 'father_ear_tag', 'mother_ear_tag', 'acquisition_source',
            'acquisition_date', 'acquisition_cost', 'status', 'notes',
            ];

            foreach ($query->lazyById(500) as $pig) {
                $ss = $pig->sale_status;
                yield [
                    $pig->code,
                    $pig->ear_tag_number,
                    $pig->breed,
                    $pig->gender,
                    $pig->stock_type,
                    $pig->date_of_birth ? $pig->date_of_birth->format('Y-m-d') : '',
                    $pig->current_age_in_days,
                    $pig->room?->structure?->name ?? '',
                    $pig->room?->name ?? '',
                    $pig->father?->ear_tag_number ?? '',
                    $pig->mother?->ear_tag_number ?? '',
                    $pig->acquisition_source ?? '',
                    $pig->acquisition_date ? $pig->acquisition_date->format('Y-m-d') : '',
                    $pig->acquisition_cost ?? '',
                    $ss['sold'] ? 'Sold' : 'Available',
                    $pig->notes ?? '',
                ];
            }
        })();

        $path = $service->buildXlsx($rows);

        return response()->download(
            $path,
            'all_stocks_' . date('Y-m-d') . '.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function allImportCreate()
    {
        $this->authorize('create', PigManagement::class);

        return view('stocks.all.import.create');
    }

    public function allImportPreview(Request $request, StockExcelImportService $service)
    {
        $this->authorize('create', PigManagement::class);

        $data = $request->validate([
            'stock_file' => ['required', 'file', 'mimes:xlsx', 'max:4096'],
        ]);

        $preview = $service->preview($data['stock_file']);
        $request->session()->put('stock_import_preview', $preview);

        return view('stocks.all.import.preview', compact('preview'));
    }

    public function allImportConfirm(Request $request, StockExcelImportService $service)
    {
        $this->authorize('create', PigManagement::class);

        $preview = $request->session()->get('stock_import_preview');
        if (! $preview) {
            return redirect()->route('all-stocks.import.create')
                ->withErrors(['stock_file' => 'Upload and preview an Excel file before confirming import.']);
        }

        try {
            $result = $service->import($preview, $request->user());
        } catch (StockValidationException $e) {
            return redirect()->route('all-stocks.import.create')
                ->withErrors([$e->field => $e->getMessage()]);
        }

        $request->session()->forget('stock_import_preview');

        return redirect()->route('all-stocks.index')
            ->with('success', $result['created_count'] . ' stock record(s) imported successfully.');
    }

    public function allImportSample(StockExcelImportService $service)
    {
        $this->authorize('viewAny', PigManagement::class);

        $path = $service->buildXlsx($service->sampleRows());

        return response()->download(
            $path,
            'all_stock_import_sample.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function allExportPdf(Request $request)
    {
        $this->authorize('viewAny', PigManagement::class);
        $query = PigManagement::active()->with(['latestWeight', 'sale', 'father', 'mother']);
        $this->applyBasicFilters($query, $request);
        if ($request->stock_type) $query->where('stock_type', $request->stock_type);

        $pigs = $query->get();
        $pdf = Pdf::loadView('stocks.all.pdf', compact('pigs'));
        return $pdf->download('all_stocks_' . date('Y-m-d') . '.pdf');
    }

    public function standardExportCsv(Request $request)
    {
        $this->authorize('viewAny', PigManagement::class);
        $query = PigManagement::active()->where('stock_type', 'standard')->with(['latestWeight', 'sale']);
        $this->applyBasicFilters($query, $request);

        return $this->streamCsv('standard_stocks_' . date('Y-m-d') . '.csv', $query->get(), [
            'Code', 'Ear Tag', 'Breed', 'Gender', 'Date of Birth', 'Age (days)',
            'Breeding Ready', 'Age Status', 'Weight Status', 'Current Weight',
            'Sale Status', 'Sale Price', 'Sale Date', 'Buyer', 'Father', 'Mother',
        ], function ($pig) {
            $r  = $pig->breeding_readiness;
            $ss = $pig->sale_status;
            return [
                $pig->code,
                $pig->ear_tag_number,
                $pig->breed,
                $pig->gender === 'M' ? 'Male' : 'Female',
                $pig->date_of_birth ? $pig->date_of_birth->format('Y-m-d') : 'Not set',
                $pig->current_age_in_days,
                $r['ready'] ? 'Yes' : 'No',
                ucfirst(str_replace('_', ' ', $r['age_status'])),
                ucfirst(str_replace('_', ' ', $r['factors']['weight']['status'])),
                $pig->latestWeight ? $pig->latestWeight->weight . ' kg' : 'No data',
                $ss['sold'] ? 'Sold' : 'Available',
                $ss['sold'] && $pig->sale ? $pig->sale->formatted_sale_price : 'N/A',
                $ss['sold'] && $pig->sale ? $pig->sale->sale_date->format('Y-m-d') : 'N/A',
                $ss['sold'] && $pig->sale ? $pig->sale->buyer_name : 'N/A',
                $pig->father ? $pig->father->ear_tag_number : 'N/A',
                $pig->mother ? $pig->mother->ear_tag_number : 'N/A',
            ];
        });
    }

    public function standardExportPdf(Request $request)
    {
        $this->authorize('viewAny', PigManagement::class);
        $query = PigManagement::active()->where('stock_type', 'standard')->with(['latestWeight', 'sale', 'father', 'mother']);
        $this->applyBasicFilters($query, $request);

        $pigs = $query->get();
        $pdf = Pdf::loadView('stocks.standard.pdf', compact('pigs'));
        return $pdf->download('standard_stocks_' . date('Y-m-d') . '.pdf');
    }

    /**
     * Shared streamed-CSV helper: writes header row + one row per model.
     */
    protected function streamCsv(string $filename, iterable $rows, array $headers, callable $rowMapper): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($rows, $headers, $rowMapper) {
            $handle = fopen('php://output', 'w');
            if ($headers !== []) {
                fputcsv($handle, $headers);
            }
            foreach ($rows as $row) {
                fputcsv($handle, $rowMapper($row));
            }
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        return $response;
    }

    // ---------------------------------------------------------------- ear-tag API

    public function checkEarTag(Request $request)
    {
        $this->authorize('viewAny', PigManagement::class);
        $request->validate(['ear_tag_number' => 'required|string|max:50']);

        $earTagNumber = trim((string) $request->ear_tag_number);
        $exists       = PigManagement::where('ear_tag_number', $earTagNumber)->exists();

        return response()->json(['exists' => $exists, 'ear_tag_number' => $earTagNumber]);
    }

    // ---------------------------------------------------------------- weight upsert (operational)

    public function updateWeaningWeight(Request $request, $id)
    {
        $pig = PigManagement::findOrFail($id);
        $this->authorize('update', $pig);

        $data = $request->validate([
            'weight'     => ['required', 'numeric', 'min:0', 'max:100'],
            'weigh_date' => ['required', 'date', 'before_or_equal:today'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($pig, $data) {
            $weighDate = \Carbon\Carbon::parse($data['weigh_date'])->toDateString();
            $weight    = PigWeight::where('pig_id', $pig->id)->whereDate('weigh_date', $weighDate)->first();

            $payload = [
                'pig_id'       => $pig->id,
                'weight'       => $data['weight'],
                'weigh_date'   => $weighDate,
                'notes'        => $data['notes'] ?? null,
                'weighed_by'   => auth()->user()->name,
                'purpose'      => 'weaning_tracking',
                'weight_unit'  => 'kg',
                'is_estimated' => false,
            ];

            $weight = $weight ? tap($weight)->update($payload) : PigWeight::create($payload);

            \App\Models\WeightAlert::where('weight_record_id', $weight->id)->delete();
            \App\Models\WeightAlert::createFromWeight($weight, $pig);
        });

        return back()->with('success', "Weight updated for piglet {$pig->ear_tag_number}! New weight: {$data['weight']}kg");
    }
}
