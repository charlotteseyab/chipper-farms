<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Http\Requests\Health\MedicineRequest;
use App\Models\Medicine;
use App\Services\Health\MedicineCatalogueService;
use App\Services\Health\MedicineStockService;

/** The medicine catalogue. Each medicine's batches and stock are on its own page (MedicineStockController). */
class MedicineController extends Controller
{
    public function index(MedicineStockService $stock)
    {
        $this->authorize('viewAny', Medicine::class);
        $medicines = Medicine::orderByDesc('is_active')->orderBy('name')->paginate(30);

        return view('health.medicines.index', ['medicines' => $medicines, 'onHand' => $stock->onHandByMedicine($medicines->modelKeys())]);
    }

    public function create()
    {
        $this->authorize('create', Medicine::class);

        return view('health.medicines.form', ['medicine' => null]);
    }

    public function store(MedicineRequest $request, MedicineCatalogueService $service)
    {
        $medicine = $service->save($request->user(), $request->validated());

        return redirect()->route('medicines.show', $medicine)->with('success', 'Medicine added to the catalogue. Receive a batch to put it in stock.');
    }

    public function edit(Medicine $medicine)
    {
        $this->authorize('update', $medicine);

        return view('health.medicines.form', compact('medicine'));
    }

    public function update(MedicineRequest $request, Medicine $medicine, MedicineCatalogueService $service)
    {
        $service->save($request->user(), $request->validated(), $medicine);

        return redirect()->route('medicines.index')->with('success', 'Medicine updated. Doses already given keep the details they were given with.');
    }
}
