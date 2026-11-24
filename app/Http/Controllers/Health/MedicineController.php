<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Http\Requests\Health\MedicineRequest;
use App\Models\Medicine;
use App\Services\Health\HealthAttachmentService;
use App\Services\Health\MedicineCatalogueService;
use App\Services\Health\MedicineStockService;

/** The medicine catalogue. Each medicine's batches and stock are on its own page (MedicineStockController). */
class MedicineController extends Controller
{
    public function index(MedicineStockService $stock)
    {
        $this->authorize('viewAny', Medicine::class);
        $medicines = Medicine::with('photo')->orderByDesc('is_active')->orderBy('name')->paginate(25)->withQueryString();

        return view('health.medicines.index', ['medicines' => $medicines, 'onHand' => $stock->onHandByMedicine($medicines->modelKeys()), 'glance' => app(\App\Services\Health\HealthGlanceService::class)->catalogue()]);
    }

    public function create()
    {
        $this->authorize('create', Medicine::class);

        return view('health.medicines.form', ['medicine' => null]);
    }

    public function store(MedicineRequest $request, MedicineCatalogueService $service)
    {
        $medicine = $service->save($request->user(), $request->validated());
        $this->savePhoto($request, $medicine);

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
        $this->savePhoto($request, $medicine);

        return redirect()->route('medicines.index')->with('success', 'Medicine updated. Doses already given keep the details they were given with.');
    }

    private function savePhoto(MedicineRequest $request, Medicine $medicine): void
    {
        $files = app(HealthAttachmentService::class);
        if ($request->hasFile('photo')) {
            $files->replacePhoto($request->user(), $medicine, $request->file('photo'));
        } elseif ($request->boolean('remove_photo') && ($photo = $medicine->photo()->first())) {
            $files->remove($request->user(), $photo, 'Photo removed from the catalogue');
        }
    }
}
