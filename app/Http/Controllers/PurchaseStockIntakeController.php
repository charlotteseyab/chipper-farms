<?php

namespace App\Http\Controllers;

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
            'breeds' => \App\Models\Setting::animalBreeds(),
            'healthStatuses' => PurchaseStockIntakeService::HEALTH_STATUSES,
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
                ->get()
                ->filter(function (Room $room) use ($purchaseStockIntake): bool {
                    if ($room->capacity === null || (int) $room->capacity <= 0) return true;
                    $available = (int) $room->capacity - (int) $room->active_pigs_count;
                    return $available >= max(1, $purchaseStockIntake->clearedQuantity());
                })->values(),
            'statuses' => $this->statuses(),
            'proposedEarTags' => $this->service->proposeEarTagNumbers($purchaseStockIntake->clearedQuantity(), (int) $purchaseStockIntake->organization_id),
            'tagColors' => \App\Models\EarTagColor::where('is_active', true)
                ->where('normalized_name', '!=', 'unspecified')->orderBy('name')->get(),
            'canMaterializeLegacyPiglets' => $canMaterializeLegacyPiglets,
            'healthStatuses' => PurchaseStockIntakeService::HEALTH_STATUSES,
            'proposedGenders' => $this->service->proposedGenders($purchaseStockIntake),
        ]);
    }

    public function receive(Request $request, PurchaseStockIntake $purchaseStockIntake)
    {
        $this->authorize('receive', $purchaseStockIntake);

        $data = $request->validate([
            'quantity_received' => ['required', 'integer', 'min:1'],
            'quantity_rejected' => ['nullable', 'integer', 'min:0'],
            'health_status' => ['nullable', Rule::in(array_keys(PurchaseStockIntakeService::HEALTH_STATUSES))],
            'health_notes' => ['nullable', 'string', 'max:3000'],
        ], [
            'quantity_received.min' => 'At least one animal must arrive. If none did, cancel the intake instead.',
        ]);

        return $this->runAction(fn () => $this->service->receive($purchaseStockIntake, $data), 'Purchased stock received.');
    }

    public function quarantine(Request $request, PurchaseStockIntake $purchaseStockIntake)
    {
        $this->authorize('quarantine', $purchaseStockIntake);

        $data = $request->validate([
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')->where(
                fn ($query) => $query->where('organization_id', $this->currentOrganization->requireId())
            )],
            'health_status' => ['nullable', Rule::in(array_keys(PurchaseStockIntakeService::HEALTH_STATUSES))],
            'health_notes' => ['nullable', 'string', 'max:3000'],
            'quarantine_release_date' => ['nullable', 'date', 'after_or_equal:' . $purchaseStockIntake->purchase_date->toDateString()],
        ], [
            'room_id.required' => 'Select the physical room where these animals will be quarantined.',
        ]);

        return $this->runAction(fn () => $this->service->quarantine($purchaseStockIntake, $data), 'Purchased stock moved into quarantine/intake housing.');
    }

    public function approve(PurchaseStockIntake $purchaseStockIntake)
    {
        $this->authorize('approve', $purchaseStockIntake);

        return $this->runAction(fn () => $this->service->approve($purchaseStockIntake), 'Purchased stock intake approved.');
    }

    public function reviewQuarantine(Request $request, PurchaseStockIntake $purchaseStockIntake)
    {
        $this->authorize('reviewQuarantine', $purchaseStockIntake);

        $data = $request->validate([
            'quarantine_outcome' => ['required', Rule::in([
                PurchaseStockIntake::QUARANTINE_CLEARED,
                PurchaseStockIntake::QUARANTINE_EXTENDED,
                PurchaseStockIntake::QUARANTINE_PARTIALLY_CLEARED,
                PurchaseStockIntake::QUARANTINE_REJECTED,
            ])],
            'quarantine_rejected_quantity' => ['nullable', 'required_if:quarantine_outcome,partially_cleared', 'integer', 'min:1'],
            'quarantine_disposition' => ['nullable', 'required_if:quarantine_outcome,partially_cleared,rejected', Rule::in([
                'returned_to_supplier', 'euthanized', 'died_in_quarantine', 'isolated_elsewhere',
            ])],
            'quarantine_review_notes' => ['nullable', 'required_if:quarantine_outcome,partially_cleared,rejected', 'string', 'max:3000'],
            'quarantine_release_date' => ['nullable', 'required_if:quarantine_outcome,extended', 'date', 'after_or_equal:today'],
            'health_status' => ['nullable', Rule::in(array_keys(PurchaseStockIntakeService::HEALTH_STATUSES))],
            'health_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $messages = [
            PurchaseStockIntake::QUARANTINE_CLEARED => 'Quarantine completed. The cleared animals can now be approved for stock entry.',
            PurchaseStockIntake::QUARANTINE_EXTENDED => 'Quarantine extended. Review the animals again on the new date.',
            PurchaseStockIntake::QUARANTINE_PARTIALLY_CLEARED => 'Quarantine completed with a partial clearance. Only cleared animals can enter stock.',
            PurchaseStockIntake::QUARANTINE_REJECTED => 'The intake was rejected after quarantine and cannot enter main stock.',
        ];

        return $this->runAction(
            fn () => $this->service->reviewQuarantine($purchaseStockIntake, $data),
            $messages[$data['quarantine_outcome']]
        );
    }

    public function addToStock(Request $request, PurchaseStockIntake $purchaseStockIntake)
    {
        $this->authorize('addToStock', $purchaseStockIntake);

        $data = $request->validate($this->addToStockRules($purchaseStockIntake));

        return $this->runAction(
            fn () => $this->service->addToStock($purchaseStockIntake, $data['ear_tag_numbers'] ?? [], [
                'room_id' => $data['room_id'] ?? null,
                'tag_color_ids' => $data['tag_color_ids'] ?? [],
                'genders' => $data['genders'] ?? [],
            ]),
            'Purchased stock added to stock.'
        );
    }

    /** Stop an intake that will not enter stock (nothing arrived, order cancelled, entered by mistake). */
    public function cancel(Request $request, PurchaseStockIntake $purchaseStockIntake)
    {
        $this->authorize('cancel', $purchaseStockIntake);

        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:1000'],
        ]);

        return $this->runAction(
            fn () => $this->service->cancel($purchaseStockIntake, $data['cancellation_reason']),
            'Purchased stock intake cancelled.'
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
            // Animals already bought: not in the future, and not decades ago.
            'purchase_date' => ['required', 'date', 'before_or_equal:today', 'after:' . now()->subYears(15)->toDateString()],
            'document_reference' => ['nullable', 'string', 'max:255'],
            'animal_category' => ['required', Rule::in(array_keys($this->categories()))],
            // Every pig needs one of the farm's breeds; without it the pigs
            // were created with breed "unknown".
            'breed' => ['required', Rule::in(array_keys(\App\Models\Setting::animalBreeds()))],
            'quantity_ordered' => ['required', 'integer', 'min:1', 'max:1000'],
            'male_quantity' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'female_quantity' => ['nullable', 'integer', 'min:0', 'max:1000'],
            // Age or date of birth: without either, every pig was dated as
            // born on the purchase date (a finisher counted as a newborn).
            'estimated_age_days' => ['nullable', 'required_without:estimated_date_of_birth', 'integer', 'min:0', 'max:5475'],
            'estimated_date_of_birth' => ['nullable', 'required_without:estimated_age_days', 'date', 'before_or_equal:purchase_date'],
            'average_weight_kg' => ['nullable', 'numeric', 'min:0.1', 'max:500'],
            // Approval needs the cost and an intake cannot be edited, so it is
            // asked for here rather than leaving the intake stuck later.
            'total_purchase_cost' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'transport_cost' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'health_status' => ['nullable', Rule::in(array_keys(PurchaseStockIntakeService::HEALTH_STATUSES))],
            'health_notes' => ['nullable', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:3000'],
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
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')->where(
                fn ($query) => $query->where('organization_id', $this->currentOrganization->requireId())
            )],
            'ear_tag_numbers' => ['required', 'array', 'size:' . $intake->clearedQuantity()],
            'ear_tag_numbers.*' => ['required', 'string', 'max:50', function (string $attribute, mixed $value, \Closure $fail) {
                $index = (int) str($attribute)->afterLast('.')->toString();
                $colorId = request()->input("tag_color_ids.{$index}");
                $identity = \App\Models\EarTag::normalizeNumber((string) $value);

                foreach ((array) request()->input('ear_tag_numbers', []) as $otherIndex => $otherNumber) {
                    if ((int) $otherIndex >= $index) continue;
                    $otherColor = request()->input("tag_color_ids.{$otherIndex}") ?: 'unspecified';
                    if ((string) ($colorId ?: 'unspecified') === (string) $otherColor && $identity === \App\Models\EarTag::normalizeNumber((string) $otherNumber)) {
                        $fail('The same colour and tag number are repeated.');
                        return;
                    }
                }

                $resolvedColorId = $colorId ?: \App\Models\EarTagColor::where('normalized_name', 'unspecified')->value('id');
                $tag = \App\Models\EarTag::where('ear_tag_color_id', $resolvedColorId)->where('normalized_tag_number', \App\Models\EarTag::normalizeNumber((string) $value))->first();
                if (! $tag && \App\Models\PigManagement::withTrashed()->whereDoesntHave('earTagAssignments')->where('ear_tag_number', trim((string) $value))->exists()) {
                    $fail('This legacy tag needs reconciliation before assignment.');
                }
                if ($tag && ($tag->status !== \App\Models\EarTag::AVAILABLE || $tag->currentAssignment()->exists())) {
                    $fail('This tag number is already in use.');
                }
            }],
            'genders' => ['required', 'array', 'size:' . $intake->clearedQuantity()],
            'genders.*' => ['required', Rule::in(['M', 'F'])],
            'tag_color_ids' => ['nullable', 'array', 'size:' . $intake->clearedQuantity()],
            'tag_color_ids.*' => ['nullable', 'integer', Rule::exists('ear_tag_colors', 'id')->where(
                fn ($query) => $query->where('organization_id', $this->currentOrganization->requireId())->where('is_active', true)->where('normalized_name', '!=', 'unspecified')
            )],
        ];
    }
}
