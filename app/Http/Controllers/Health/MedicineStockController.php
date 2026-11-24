<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Http\Requests\Health\AdjustMedicineStockRequest;
use App\Http\Requests\Health\ReceiveMedicineBatchRequest;
use App\Models\MedicationDosingAllocation;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineStockAdjustment;
use App\Services\Health\MedicineStockService;
use App\Support\CollectionPage;

/** One medicine's batches and stock: receive a batch, adjust or count it, see where it went. */
class MedicineStockController extends Controller
{
    public function show(Medicine $medicine, MedicineStockService $stock)
    {
        $this->authorize('view', $medicine);
        $batches = $medicine->batches()->orderByDesc('is_active')->orderBy('expiry_date')->get();
        $onHand = $stock->onHandByBatch($batches->modelKeys());
        $batches->each(fn ($b) => $b->setAttribute('on_hand', $onHand[$b->id] ?? '0'));
        $total = $batches->reduce(fn ($sum, $b) => bcadd($sum, $b->on_hand, 4), '0');

        $usage = MedicationDosingAllocation::with(['event.administrations.pig', 'batch'])
            ->whereIn('medicine_batch_id', $batches->modelKeys())->latest('id')->paginate(10, ['*'], 'usage_page')->withQueryString();
        $adjustments = MedicineStockAdjustment::with(['batch', 'adjustedBy'])
            ->whereIn('medicine_batch_id', $batches->modelKeys())->latest('id')->paginate(10, ['*'], 'adjustments_page')->withQueryString();
        $batchPage = CollectionPage::make($batches, 10, 'batches_page');

        return view('health.medicines.show', compact('medicine', 'batches', 'batchPage', 'total', 'usage', 'adjustments') + ['glance' => app(\App\Services\Health\HealthGlanceService::class)->medicine($medicine)]);
    }

    public function receive(ReceiveMedicineBatchRequest $request, Medicine $medicine, MedicineStockService $stock)
    {
        $batch = $stock->receive($request->user(), $medicine, $request->validated());

        return redirect()->route('medicines.show', $medicine)->with('success', "Batch {$batch->batch_number} received into stock.");
    }

    public function adjust(AdjustMedicineStockRequest $request, MedicineBatch $medicineBatch, MedicineStockService $stock)
    {
        $data = $request->validated();
        $stock->adjust($request->user(), $medicineBatch, $data['type'], $data['quantity'], $data['reason']);

        return redirect()->route('medicines.show', $medicineBatch->medicine_id)->with('success', "Stock for batch {$medicineBatch->batch_number} updated.");
    }
}
