<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\PurchaseStockIntake;
use App\Models\Room;
use App\Models\Structure;
use App\Services\Stock\PurchaseStockIntakeService;
use App\Services\Stock\StockValidationException;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseStockIntakeController extends Controller
{
    public function __construct(
        protected PurchaseStockIntakeService $service,
        protected CurrentOrganization $currentOrganization,
    )
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', PurchaseStockIntake::class);

        $query = PurchaseStockIntake::with(['room.structure', 'group'])
            ->forOrganization($this->currentOrganization->requireId())
            ->latest('purchase_date')
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('animal_category')) {
            $query->where('animal_category', $request->animal_category);
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->q);
            $query->where(function ($sub) use ($q) {
                $sub->where('batch_number', 'like', "%{$q}%")
                    ->orWhere('supplier_name', 'like', "%{$q}%")
                    ->orWhere('document_reference', 'like', "%{$q}%");
            });
        }

        $intakes = $query->paginate(12)->appends($request->query());
        $payload = [
            'intakes' => $intakes,
            'statuses' => $this->statuses(),
            'categories' => $this->categories(),
        ];

        if ($request->ajax()) {
            return response()->json([
                'html' => view('purchase-stock-intakes._table', $payload)->render(),
                'url' => $request->fullUrl(),
            ]);
        }

        return view('purchase-stock-intakes.index', $payload);
    }

    public function create()
    {
        $this->authorize('create', PurchaseStockIntake::class);

        return view('purchase-stock-intakes.create', [
            'categories' => $this->categories(),
            'structures' => $this->activeStructures(),
            'rooms' => Room::with('structure')->orderBy('name')->get(),
            'groups' => Group::orderBy('name')->get(),
            'breeds' => \App\Models\Setting::animalBreeds(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', PurchaseStockIntake::class);

        $data = $request->validate($this->baseRules());
        $this->validateGenderQuantities($data);

        try {
            $intake = $this->service->create($data);
            return redirect()->route('purchase-stock-intakes.show', $intake)
                ->with('success', 'Purchased stock intake created. Stock will increase only after receive, approval, and add-to-stock.');
        } catch (StockValidationException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }
    }

    public function show(PurchaseStockIntake $purchaseStockIntake)
    {
        $this->authorize('view', $purchaseStockIntake);

        $purchaseStockIntake->load(['room.structure', 'group', 'pigs.latestWeight', 'createdBy', 'approvedBy']);

        $canMaterializeLegacyPiglets = $purchaseStockIntake->status === PurchaseStockIntake::STATUS_ADDED_TO_STOCK
            && $purchaseStockIntake->isPigletBatch()
            && $purchaseStockIntake->pigs->isEmpty();

        return view('purchase-stock-intakes.show', [
            'intake' => $purchaseStockIntake,
            'rooms' => Room::with('structure')
                ->withCount(['pigs as active_pigs_count' => function ($query) {
                    $query->whereDoesntHave('sales')->whereDoesntHave('deaths');
                }])
                ->orderBy('name')
                ->get(),
            'groups' => Group::orderBy('name')->get(),
            'statuses' => $this->statuses(),
            'proposedEarTags' => $this->service->proposeEarTagNumbers($purchaseStockIntake->receivedQuantity(), (int) $purchaseStockIntake->organization_id),
            'canMaterializeLegacyPiglets' => $canMaterializeLegacyPiglets,
        ]);
    }

    public function receive(Request $request, PurchaseStockIntake $purchaseStockIntake)
    {
        $this->authorize('receive', $purchaseStockIntake);

        $data = $request->validate([
            'quantity_received' => ['required', 'integer', 'min:0'],
            'quantity_rejected' => ['nullable', 'integer', 'min:0'],
            'health_status' => ['nullable', 'string', 'max:40'],
            'health_notes' => ['nullable', 'string'],
        ]);

        return $this->runAction(fn () => $this->service->receive($purchaseStockIntake, $data), 'Purchased stock received.');
    }

    public function quarantine(Request $request, PurchaseStockIntake $purchaseStockIntake)
    {
        $this->authorize('quarantine', $purchaseStockIntake);

        $data = $request->validate([
            'room_id' => ['nullable', 'required_without:group_id', 'exists:rooms,id'],
            'group_id' => ['nullable', 'required_without:room_id', 'exists:groups,id'],
            'health_status' => ['nullable', 'string', 'max:40'],
            'health_notes' => ['nullable', 'string'],
            'quarantine_release_date' => ['nullable', 'date'],
        ], [
            'room_id.required_without' => 'Assign a quarantine room or group before moving purchased stock into quarantine.',
            'group_id.required_without' => 'Assign a quarantine room or group before moving purchased stock into quarantine.',
        ]);

        return $this->runAction(fn () => $this->service->quarantine($purchaseStockIntake, $data), 'Purchased stock moved into quarantine/intake housing.');
    }

    public function approve(PurchaseStockIntake $purchaseStockIntake)
    {
        $this->authorize('approve', $purchaseStockIntake);

        return $this->runAction(fn () => $this->service->approve($purchaseStockIntake), 'Purchased stock intake approved.');
    }

    public function addToStock(Request $request, PurchaseStockIntake $purchaseStockIntake)
    {
        $this->authorize('addToStock', $purchaseStockIntake);

        $data = $request->validate($this->addToStockRules($purchaseStockIntake));

        return $this->runAction(
            fn () => $this->service->addToStock($purchaseStockIntake, $data['ear_tag_numbers'] ?? [], [
                'room_id' => $data['room_id'] ?? null,
                'group_id' => $data['group_id'] ?? null,
            ]),
            'Purchased stock added to stock.'
        );
    }

    protected function runAction(callable $action, string $message)
    {
        try {
            $intake = $action();
            return redirect()->route('purchase-stock-intakes.show', $intake)->with('success', $message);
        } catch (StockValidationException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }
    }

    protected function baseRules(): array
    {
        return [
            'supplier_name' => ['required', 'string', 'max:255'],
            'purchase_date' => ['required', 'date'],
            'document_reference' => ['nullable', 'string', 'max:255'],
            'animal_category' => ['required', Rule::in(array_keys($this->categories()))],
            'breed' => ['nullable', Rule::in(array_keys(\App\Models\Setting::animalBreeds()))],
            'quantity_ordered' => ['required', 'integer', 'min:1'],
            'male_quantity' => ['nullable', 'integer', 'min:0'],
            'female_quantity' => ['nullable', 'integer', 'min:0'],
            'estimated_age_days' => ['nullable', 'integer', 'min:0'],
            'estimated_date_of_birth' => ['nullable', 'date'],
            'average_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'total_purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'transport_cost' => ['nullable', 'numeric', 'min:0'],
            'health_status' => ['nullable', 'string', 'max:40'],
            'health_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function validateGenderQuantities(array $data): void
    {
        $male = (int) ($data['male_quantity'] ?? 0);
        $female = (int) ($data['female_quantity'] ?? 0);
        $ordered = (int) ($data['quantity_ordered'] ?? 0);

        if (($male + $female) > $ordered) {
            throw ValidationException::withMessages([
                'male_quantity' => 'Male and female quantities cannot exceed the ordered quantity.',
            ]);
        }
    }

    protected function statuses(): array
    {
        return [
            PurchaseStockIntake::STATUS_PENDING_RECEIVE => 'Pending receive',
            PurchaseStockIntake::STATUS_RECEIVED => 'Received',
            PurchaseStockIntake::STATUS_QUARANTINED => 'Quarantined',
            PurchaseStockIntake::STATUS_APPROVED => 'Approved',
            PurchaseStockIntake::STATUS_ADDED_TO_STOCK => 'Added to stock',
            PurchaseStockIntake::STATUS_REJECTED => 'Rejected',
            PurchaseStockIntake::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    protected function categories(): array
    {
        return [
            PurchaseStockIntake::CATEGORY_PIGLET => 'Piglet batch',
            PurchaseStockIntake::CATEGORY_GROWER => 'Grower',
            PurchaseStockIntake::CATEGORY_FINISHER => 'Finisher',
            PurchaseStockIntake::CATEGORY_BREEDING_STOCK => 'Breeding stock',
        ];
    }

    protected function activeStructures()
    {
        $query = Structure::query()->orderBy('name');

        if (Schema::hasColumn('structures', 'status')) {
            $query->where('status', 'active');
        }

        return $query->get();
    }

    protected function addToStockRules(PurchaseStockIntake $intake): array
    {
        return [
            'room_id' => ['nullable', 'exists:rooms,id'],
            'group_id' => ['nullable', 'exists:groups,id'],
            'ear_tag_numbers' => ['required', 'array', 'size:' . $intake->receivedQuantity()],
            'ear_tag_numbers.*' => ['required', 'string', 'max:50', 'distinct', 'unique:pigs_management,ear_tag_number'],
        ];
    }
}
