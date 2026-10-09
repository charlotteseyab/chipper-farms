<?php

namespace App\Http\Controllers;

use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\PigSale;
use App\Models\PigDeath;
use App\Models\Mating;
use App\Models\Weaning;
use App\Models\Farrowing;
use App\Models\Litter;
use App\Helpers\FlashHelper;
use App\Http\Requests\Sales\RecordPigSaleRequest;
use App\Http\Requests\Sales\UpdatePaymentStatusRequest;
use App\Services\Sales\RecordPigSaleService;
use App\Services\Sales\SalesExportService;
use App\Services\Sales\UpdatePigSalePaymentStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Support\CurrentOrganization;
use App\Domain\Events\Lifecycle;
use App\Http\Controllers\MatingController;
use App\Http\Controllers\PigRelationshipController;
use App\Http\Controllers\PigStockController;
use App\Http\Controllers\PigLifecycleController;
use App\Http\Controllers\WeaningController;
use App\Http\Controllers\PigSaleController;
use App\Http\Controllers\PigDeathController;
use App\Http\Controllers\PigHealthController;
use App\Http\Controllers\PigArchiveController;
use App\Http\Requests\Stock\StorePigRequest;
use App\Http\Requests\Stock\UpdateBreedingPigRequest;
use App\Http\Requests\Stock\UpdateStandardPigRequest;
use App\Http\Requests\Deaths\RecordPigDeathRequest;
use App\Services\Deaths\RecordPigDeathService;

class PigManagementController extends Controller {

    // All Stock Management
    public function allIndex(Request $request)
    {
        return app(PigStockController::class)->allIndex($request);
    }

    // Breeding Stock Management (existing functionality)
    public function index(Request $request)
    {
        return app(PigStockController::class)->index($request);
    }

    // Standard Stock Management
    public function standardIndex(Request $request)
    {
        return app(PigStockController::class)->standardIndex($request);
    }

    public function exportCsv(Request $request)
    {
        return app(PigStockController::class)->exportCsv($request);
    }

    public function exportPdf(Request $request)
    {
        return app(PigStockController::class)->exportPdf($request);
    }

    public function create()
    {
        return app(PigStockController::class)->create();
    }

    public function standardCreate()
    {
        return app(PigStockController::class)->standardCreate();
    }
    
    public function allCreate(\App\Services\Stock\Tags\EarTagAllocator $tagAllocator)
    {
        return app(PigStockController::class)->allCreate($tagAllocator);
    }
    

    public function store(StorePigRequest $request)
    {
        return app(PigStockController::class)->store($request);
    }
    

    // --- Standard Stock Edit ---
    public function standardEdit(PigManagement $pig)
    {
        return app(PigStockController::class)->standardEdit($pig);
    }

    public function standardUpdate(UpdateStandardPigRequest $request, PigManagement $pig)
    {
        return app(PigStockController::class)->standardUpdate($request, $pig);
    }

    public function checkRelationship(Request $request)
    {
        return app(PigRelationshipController::class)->check($request);
    }

    public function destroy($id)
    {
        return app(PigStockController::class)->destroy($id);
    }

    public function edit($id)
    {
        return app(PigStockController::class)->edit($id);
    }

    public function update(UpdateBreedingPigRequest $request, $id)
    {
        return app(PigStockController::class)->update($request, $id);
    }

    // All Stock Export Methods
    public function allExportCsv(Request $request)
    {
        return app(PigStockController::class)->allExportCsv($request);
    }

    public function allExportPdf(Request $request)
    {
        return app(PigStockController::class)->allExportPdf($request);
    }

    // Standard Stock Export Methods
    public function standardExportCsv(Request $request)
    {
        return app(PigStockController::class)->standardExportCsv($request);
    }

    public function standardExportPdf(Request $request)
    {
        return app(PigStockController::class)->standardExportPdf($request);
    }

    // Mating Planner Methods
    public function matingIndex()
    {
        return app(MatingController::class)->matingIndex();
    }

    public function planMating(Request $request)
    {
        return app(MatingController::class)->planMating($request);
    }

    public function matingManage(Request $request)
    {
        return app(MatingController::class)->matingManage($request);
    }

    public function destroyMating($id)
    {
        return app(MatingController::class)->destroyMating($id);
    }
    

    public function recordPregnancyCheck(Request $request, $id)
    {
        return app(MatingController::class)->recordPregnancyCheck($request, $id);
    }

    public function completeMating(Request $request, $id)
    {
        return app(MatingController::class)->completeMating($request, $id);
    }

    public function cancelMating(Request $request, $id)
    {
        return app(MatingController::class)->cancelMating($request, $id);
    }

    // Gestation Period Management Methods
    public function gestationIndex(Request $request)
    {
        return app(MatingController::class)->gestationIndex($request);
    }

    public function destroyGestation(Mating $mating)
    {
        return app(MatingController::class)->destroyGestation($mating);
    }
    
    public function recordFarrowing(Request $request, $id)
    {
        return app(MatingController::class)->recordFarrowing($request, $id);
    }

    public function updateGestationStatus(Request $request, $id)
    {
        return app(MatingController::class)->updateGestationStatus($request, $id);
    }

    public function editGestation(Mating $mating)
    {
        return app(MatingController::class)->editGestation($mating);
    }

    public function updateGestation(Request $request, Mating $mating)
    {
        return app(MatingController::class)->updateGestation($request, $mating);
    }

    // Unified Weaning Dashboard - Litter-Based Approach
    public function weaningDashboard(Request $request)
    {
        return app(WeaningController::class)->dashboard($request);
    }

    /**
     * Batch-wean an entire litter (Farrowing record) from the unified weaning dashboard.
     * This marks the litter as no longer active so it drops out of the \"Ready Now/Due Soon\" lists.
     */
    public function weanLitter(Request $request, Farrowing $farrowing)
    {
        return app(WeaningController::class)->weanLitter($request, $farrowing);
    }

    /**
     * Shows pigs that have been weaned but have not yet completed maturity.
     */
    public function weaningInProgress()
    {
        return app(WeaningController::class)->inProgress();
    }

    /**
     * Shows pigs that have completed the full weaning-to-maturity journey.
     */
    public function weaningWeaned()
    {
        return app(WeaningController::class)->weaned();
    }

    /**
     * Shows pigs that are overdue for weaning (7+ days past weaning age).
     * Also fetches Sows with litters that are past the overdue threshold for group management context.
     */
    public function weaningOverdue()
    {
        return app(WeaningController::class)->overdue();
    }
    

    public function archived(Request $request)
    {
        return app(PigArchiveController::class)->index($request);
    }

    public function restorePigletDeath(Request $request, $id)
    {
        return app(PigArchiveController::class)->restorePigletDeath(
            $request,
            app(\App\Services\Archive\RestoreArchivedRecordService::class),
            $id,
        );
    }

    public function restorePig(Request $request, $id)
    {
        return app(PigArchiveController::class)->restorePig(
            $request,
            app(\App\Services\Archive\RestoreArchivedRecordService::class),
            $id,
        );
    }

    public function recordWeaning(Request $request, $id)
    {
        return app(WeaningController::class)->recordWeaning($request, $id);
    }

    public function completeMaturity(Request $request, $id)
    {
        return app(WeaningController::class)->completeMaturity($request, $id);
    }

    public function updateWeaningStatus(Request $request, $id)
    {
        return app(WeaningController::class)->updateWeaningStatus($request, $id);
    }

    // Promote pig from standard to breeding stock
    public function promoteToBreeding(Request $request, $id)
    {
        return app(PigLifecycleController::class)->promote(
            $request,
            app(\App\Services\Lifecycle\PromotePigService::class),
            $id,
        );
    }

    // Demote pig from breeding to standard stock
    public function demoteToStandard(Request $request, $id)
    {
        return app(PigLifecycleController::class)->demote(
            $request,
            app(\App\Services\Lifecycle\DemotePigService::class),
            $id,
        );
    }

    // Show detailed pig information
    public function show($id)
    {
        return app(PigStockController::class)->show($id);
    }

    /**
     * Export a single pig's detail profile as PDF.
     */
    public function exportPigPdf($id)
    {
        return app(PigStockController::class)->exportPigPdf($id);
    }

    // Sale Management Methods
    public function saleForm($id)
    {
        return app(PigSaleController::class)->form($id);
    }

    public function recordSale(RecordPigSaleRequest $request, RecordPigSaleService $service, $id)
    {
        return app(PigSaleController::class)->record($request, $service, $id);
    }

    // Sales Management
    public function salesIndex(Request $request, SalesExportService $exportService)
    {
        return app(PigSaleController::class)->index($request, $exportService);
    }

    public function saleShow($id)
    {
        return app(PigSaleController::class)->show($id);
    }

    public function updatePaymentStatus(UpdatePaymentStatusRequest $request, UpdatePigSalePaymentStatusService $service, $id)
    {
        return app(PigSaleController::class)->updatePaymentStatus($request, $service, $id);
    }

    // Sales Export Methods
    public function salesExportCsv(Request $request, SalesExportService $service)
    {
        return app(PigSaleController::class)->exportCsv($request, $service);
    }

    public function salesExportPdf(Request $request, SalesExportService $service)
    {   
        return app(PigSaleController::class)->exportPdf($request, $service);
    }

    // Death Management Methods
    public function deathForm($id)
    {
        return app(PigDeathController::class)->form($id);
    }

    public function recordDeath(
        RecordPigDeathRequest $request,
        RecordPigDeathService $service,
        $id,
        DispatchDomainEvent $dispatcher,
        CurrentOrganization $currentOrganization
    )
    {
        return app(PigDeathController::class)->record($request, $service, $id);
    }

    public function deathsIndex(Request $request)
    {
        return app(PigDeathController::class)->index($request);
    }

    public function deathShow($id)
    {
        return app(PigDeathController::class)->show($id);
    }

    // Death Export Methods
    public function deathsExportCsv(Request $request)
    {
        return app(PigDeathController::class)->exportCsv(
            $request,
            app(\App\Services\Deaths\DeathsExportService::class),
        );
    }

    public function deathsExportPdf(Request $request)
    {   
        return app(PigDeathController::class)->exportPdf(
            $request,
            app(\App\Services\Deaths\DeathsExportService::class),
        );
    }


    public function updateWeaningWeight(Request $request, $id)
    {
        return app(PigStockController::class)->updateWeaningWeight($request, $id);
    }

    /**
     * Check if an ear tag number already exists (for real-time validation)
     */
    public function checkEarTag(Request $request)
    {
        return app(PigStockController::class)->checkEarTag($request);
    }
}
