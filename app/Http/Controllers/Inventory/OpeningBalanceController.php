<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ImportOpeningBalanceCsvRequest;
use App\Http\Requests\Inventory\StoreOpeningBalanceRequest;
use App\Models\FeedType;
use App\Models\Finance\InventoryOpeningBalanceImport;
use App\Models\RawMaterial;
use App\Services\Finance\Inventory\DuplicateImportException;
use App\Services\Finance\Inventory\OpeningBalanceService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Phase 4 — opening balance management UI.
 *
 * Two entry paths:
 *   1. Manual form: pick item, qty, unit_cost per row, submit.
 *   2. CSV upload: header row + N data rows, hashed for dedup.
 *
 * Both routes terminate at OpeningBalanceService::ingestRawMaterials
 * or ingestFinishedFeed.
 */
class OpeningBalanceController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected OpeningBalanceService $service,
    ) {
        $this->middleware('auth');
        $this->middleware('permission:view_inventory_value')->only(['index', 'show']);
        $this->middleware('permission:manage_opening_balances')->except(['index', 'show']);
    }

    public function index(Request $request)
    {
        $orgId = $this->currentOrganization->id();
        $imports = InventoryOpeningBalanceImport::query()
            ->with('performedBy')
            ->when(
                $orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->orderByDesc('id')
            ->paginate(25);

        return view('inventory.opening-balances.index', [
            'imports' => $imports,
        ]);
    }

    public function show(InventoryOpeningBalanceImport $import)
    {
        $this->ensureInOrg($import);
        $import->load(['performedBy', 'movements']);

        return view('inventory.opening-balances.show', [
            'import' => $import,
        ]);
    }

    public function create(Request $request)
    {
        $kind = $request->query('kind', InventoryOpeningBalanceImport::KIND_RAW_MATERIAL);
        if (! in_array($kind, InventoryOpeningBalanceImport::ALLOWED_KINDS, true)) {
            $kind = InventoryOpeningBalanceImport::KIND_RAW_MATERIAL;
        }

        return view('inventory.opening-balances.create', [
            'kind'       => $kind,
            'materials'  => $kind === InventoryOpeningBalanceImport::KIND_RAW_MATERIAL
                ? RawMaterial::query()->orderBy('name')->get(['id', 'name', 'unit', 'category'])
                : collect(),
            'feedTypes'  => $kind === InventoryOpeningBalanceImport::KIND_FINISHED_FEED
                ? FeedType::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    public function store(StoreOpeningBalanceRequest $request)
    {
        $validated = $request->validated();

        try {
            $cutover = CarbonImmutable::parse($validated['cutover_date']);

            $rows = collect($validated['rows'])->map(function ($row) use ($validated) {
                if ($validated['item_kind'] === InventoryOpeningBalanceImport::KIND_RAW_MATERIAL) {
                    return [
                        'raw_material_id' => (int) $row['raw_material_id'],
                        'quantity'        => (float) $row['quantity'],
                        'unit_cost'       => (float) $row['unit_cost'],
                    ];
                }
                return [
                    'feed_type_id' => (int) $row['feed_type_id'],
                    'quantity'     => (float) $row['quantity'],
                    'unit_cost'    => (float) $row['unit_cost'],
                ];
            })->all();

            $import = $validated['item_kind'] === InventoryOpeningBalanceImport::KIND_RAW_MATERIAL
                ? $this->service->ingestRawMaterials(
                    rows:        $rows,
                    cutoverDate: $cutover,
                    actor:       $request->user(),
                    importMode:  InventoryOpeningBalanceImport::MODE_MANUAL,
                    notes:       $validated['notes'] ?? null,
                )
                : $this->service->ingestFinishedFeed(
                    rows:        $rows,
                    cutoverDate: $cutover,
                    actor:       $request->user(),
                    importMode:  InventoryOpeningBalanceImport::MODE_MANUAL,
                    notes:       $validated['notes'] ?? null,
                );

            return redirect()
                ->route('inventory.opening-balances.show', $import)
                ->with('success', "Opening balances ingested for {$import->row_count} row(s).");
        } catch (DuplicateImportException $e) {
            return redirect()
                ->route('inventory.opening-balances.show', $e->existingId)
                ->with('warning', 'A previous import with identical payload already exists.');
        } catch (\Throwable $e) {
            Log::error('OpeningBalanceController::store failed', ['error' => $e->getMessage()]);
            return redirect()->back()
                ->withInput()
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    public function showImport(Request $request)
    {
        $kind = $request->query('kind', InventoryOpeningBalanceImport::KIND_RAW_MATERIAL);
        if (! in_array($kind, InventoryOpeningBalanceImport::ALLOWED_KINDS, true)) {
            $kind = InventoryOpeningBalanceImport::KIND_RAW_MATERIAL;
        }

        return view('inventory.opening-balances.import', [
            'kind' => $kind,
        ]);
    }

    public function import(ImportOpeningBalanceCsvRequest $request)
    {
        $kind = $request->validated()['item_kind'];
        $cutover = CarbonImmutable::parse($request->validated()['cutover_date']);
        $file = $request->file('file');
        $filename = $file->getClientOriginalName();
        $contents = file_get_contents($file->getRealPath());

        try {
            $rows = $this->parseCsv($kind, $contents);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'CSV parse failed: ' . $e->getMessage());
        }

        try {
            $import = $kind === InventoryOpeningBalanceImport::KIND_RAW_MATERIAL
                ? $this->service->ingestRawMaterials(
                    rows:             $rows,
                    cutoverDate:      $cutover,
                    actor:            $request->user(),
                    importMode:       InventoryOpeningBalanceImport::MODE_CSV,
                    originalFilename: $filename,
                    sourcePayload:    $contents,
                    notes:            $request->validated()['notes'] ?? null,
                )
                : $this->service->ingestFinishedFeed(
                    rows:             $rows,
                    cutoverDate:      $cutover,
                    actor:            $request->user(),
                    importMode:       InventoryOpeningBalanceImport::MODE_CSV,
                    originalFilename: $filename,
                    sourcePayload:    $contents,
                    notes:            $request->validated()['notes'] ?? null,
                );

            return redirect()
                ->route('inventory.opening-balances.show', $import)
                ->with('success', "Imported {$import->row_count} row(s) from {$filename}.");
        } catch (DuplicateImportException $e) {
            return redirect()
                ->route('inventory.opening-balances.show', $e->existingId)
                ->with('warning', 'Identical CSV already imported.');
        } catch (\Throwable $e) {
            Log::error('OpeningBalanceController::import failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    // ============================================================
    // Internals
    // ============================================================

    protected function ensureInOrg(InventoryOpeningBalanceImport $import): void
    {
        $orgId = $this->currentOrganization->id();
        if ($import->organization_id !== null && $import->organization_id !== $orgId) {
            abort(404);
        }
    }

    /**
     * Parses a CSV file into rows of the correct shape for the service.
     *
     * Expected headers:
     *   raw materials: raw_material_id,quantity,unit_cost
     *   finished feed: feed_type_id,quantity,unit_cost
     *
     * Whitespace tolerance: header values trimmed; data values trimmed.
     * Numeric values: PHP float casting (handles 1, 1.0, "1,000.50" no —
     * we reject commas as thousands separators since this is an audit
     * import).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseCsv(string $kind, string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);
        $lines = array_values(array_filter(explode("\n", $contents), fn ($l) => trim($l) !== ''));

        if (count($lines) < 2) {
            throw new \InvalidArgumentException('CSV must have a header row and at least one data row.');
        }

        $expected = $kind === InventoryOpeningBalanceImport::KIND_RAW_MATERIAL
            ? ['raw_material_id', 'quantity', 'unit_cost']
            : ['feed_type_id', 'quantity', 'unit_cost'];

        $header = array_map('trim', str_getcsv((string) array_shift($lines)));
        if (array_map('strtolower', $header) !== $expected) {
            throw new \InvalidArgumentException(
                'CSV header must be exactly: ' . implode(',', $expected)
            );
        }

        $rows = [];
        foreach ($lines as $i => $line) {
            $cells = str_getcsv($line);
            if (count($cells) !== 3) {
                throw new \InvalidArgumentException(
                    "Row " . ($i + 2) . ": expected 3 columns, got " . count($cells)
                );
            }
            $idVal = trim($cells[0]);
            $qty   = trim($cells[1]);
            $cost  = trim($cells[2]);

            if (str_contains($qty, ',') || str_contains($cost, ',')) {
                throw new \InvalidArgumentException(
                    "Row " . ($i + 2) . ": use '.' as decimal separator; no thousands separators."
                );
            }

            $row = ['quantity' => (float) $qty, 'unit_cost' => (float) $cost];
            if ($kind === InventoryOpeningBalanceImport::KIND_RAW_MATERIAL) {
                $row['raw_material_id'] = (int) $idVal;
            } else {
                $row['feed_type_id'] = (int) $idVal;
            }
            $rows[] = $row;
        }
        return $rows;
    }
}
