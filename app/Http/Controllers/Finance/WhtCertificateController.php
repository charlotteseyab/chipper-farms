<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Counterparty;
use App\Models\Finance\WhtCertificate;
use App\Services\Finance\WithholdingCertificates\WhtCertificateService;
use App\Services\Finance\WithholdingCertificates\WhtPeriodCalculator;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * WHT certificate controller — Phase 3d.
 *
 * Permission gates:
 *   index, show, create, store, refresh, downloadPdf, void  : manage_wht_certificates
 *   issue                                                   : issue_wht_certificate
 *
 * The split is deliberate:
 *   - Drafts are an accountant-level workflow (review and prepare).
 *   - Issuing is a treasurer/admin act because it's the formal regulatory
 *     filing point with GRA and triggers the allocation-reversal lock.
 */
class WhtCertificateController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg)
    {
        $this->ensureViewPermission($request);
        $orgId = $currentOrg->id();
        $status     = $request->get('status', 'all');
        $supplierId = $request->get('supplier_id');
        $year       = $request->get('year');
        $index      = $request->get('period_index');

        $query = WhtCertificate::with(['counterparty:id,name,code'])
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->latest('id');

        if (in_array($status, WhtCertificate::ALL_STATUSES, true)) {
            $query->where('status', $status);
        }
        if ($supplierId) {
            $query->where('counterparty_id', $supplierId);
        }
        if ($year) {
            $query->where('period_year', (int) $year);
        }
        if ($index) {
            $query->where('period_index', (int) $index);
        }

        $certs = $query->paginate(25)->withQueryString();

        $counts = [
            'total'  => WhtCertificate::query()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
            'draft'  => WhtCertificate::query()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->draft()->count(),
            'issued' => WhtCertificate::query()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->issued()->count(),
            'void'   => WhtCertificate::query()->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->void()->count(),
        ];

        $suppliers = Counterparty::suppliers()->active()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $years = WhtCertificate::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->select('period_year')->distinct()->orderByDesc('period_year')
            ->pluck('period_year')->all();

        return view('finance.wht-certificates.index', compact(
            'certs', 'counts', 'status', 'supplierId', 'year', 'index', 'suppliers', 'years'
        ));
    }

    public function create(Request $request, CurrentOrganization $currentOrg, WhtPeriodCalculator $calc)
    {
        $this->ensureManagePermission($request);
        $orgId = $currentOrg->id();
        $suppliers = Counterparty::suppliers()->active()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'tax_id', 'currency_code']);

        $today = \Carbon\CarbonImmutable::today();
        $defaultPeriod = $calc->periodForDate($today, WhtCertificate::PERIOD_KIND_QUARTER);

        $preselectId = $request->integer('counterparty_id') ?: null;

        return view('finance.wht-certificates.create', compact(
            'suppliers', 'defaultPeriod', 'preselectId'
        ));
    }

    public function store(
        Request $request,
        WhtCertificateService $service,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureManagePermission($request);
        $data = $request->validate([
            'counterparty_id' => ['required', 'integer', 'exists:counterparties,id'],
            'period_kind'     => ['required', Rule::in([
                WhtCertificate::PERIOD_KIND_QUARTER,
                WhtCertificate::PERIOD_KIND_MONTH,
                WhtCertificate::PERIOD_KIND_YEAR,
            ])],
            'period_year'     => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_index'    => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $supplier = Counterparty::findOrFail($data['counterparty_id']);
        $this->ensureSupplierInOrg($supplier, $currentOrg);

        try {
            $cert = $service->generateDraft(
                supplier:  $supplier,
                kind:      $data['period_kind'],
                year:      (int) $data['period_year'],
                index:     (int) $data['period_index'],
                createdBy: $request->user(),
            );
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('finance.wht-certificates.show', $cert)
            ->with('success', 'Draft certificate generated.');
    }

    public function show(WhtCertificate $cert, CurrentOrganization $currentOrg)
    {
        $this->ensureViewPermission();
        $this->ensureInOrg($cert, $currentOrg);

        $cert->load([
            'counterparty:id,name,code,currency_code,tax_id',
            'createdBy:id,name',
            'issuedBy:id,name',
            'voidedBy:id,name',
        ]);

        return view('finance.wht-certificates.show', compact('cert'));
    }

    public function refresh(
        Request $request,
        WhtCertificate $cert,
        WhtCertificateService $service,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureManagePermission($request);
        $this->ensureInOrg($cert, $currentOrg);

        try {
            $service->refreshDraft($cert);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Certificate totals refreshed.');
    }

    public function issue(
        Request $request,
        WhtCertificate $cert,
        WhtCertificateService $service,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureManagePermission($request);
        $this->ensureInOrg($cert, $currentOrg);

        if (! $request->user()?->hasPermission('issue_wht_certificate')) {
            abort(403);
        }

        try {
            $service->issue($cert, $request->user());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Certificate issued and PDF generated.');
    }

    public function voidCertificate(
        Request $request,
        WhtCertificate $cert,
        WhtCertificateService $service,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureManagePermission($request);
        $this->ensureInOrg($cert, $currentOrg);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        try {
            $service->void($cert, $request->user(), $data['reason']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Certificate voided.');
    }

    public function downloadPdf(WhtCertificate $cert, CurrentOrganization $currentOrg)
    {
        $this->ensureViewPermission();
        $this->ensureInOrg($cert, $currentOrg);

        if (! $cert->pdf_disk || ! $cert->pdf_path) {
            abort(404, 'No PDF stored for this certificate.');
        }

        if (! Storage::disk($cert->pdf_disk)->exists($cert->pdf_path)) {
            abort(404, 'PDF file missing from storage.');
        }

        $filename = ($cert->certificate_number ?: "wht-{$cert->id}") . '.pdf';

        return Storage::disk($cert->pdf_disk)->download($cert->pdf_path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /* internals */

    protected function ensureInOrg(WhtCertificate $cert, CurrentOrganization $currentOrg): void
    {
        $orgId = $currentOrg->id();
        if ($orgId !== null && $cert->organization_id !== null
            && (int) $cert->organization_id !== (int) $orgId) {
            abort(403);
        }
    }

    protected function ensureSupplierInOrg(Counterparty $supplier, CurrentOrganization $currentOrg): void
    {
        $orgId = $currentOrg->id();
        if ($orgId !== null && $supplier->organization_id !== null
            && (int) $supplier->organization_id !== (int) $orgId) {
            abort(403);
        }
        if (! $supplier->isSupplier()) {
            abort(422, "Counterparty {$supplier->id} is not a supplier.");
        }
    }

    protected function ensureManagePermission(?Request $request = null): void
    {
        $user = $request?->user() ?? auth()->user();
        if (! $user?->hasPermission('manage_wht_certificates')) {
            abort(403);
        }
    }

    protected function ensureViewPermission(?Request $request = null): void
    {
        $user = $request?->user() ?? auth()->user();
        if (
            ! $user?->hasPermission('view_finance')
            && ! $user?->hasPermission('manage_wht_certificates')
        ) {
            abort(403);
        }
    }
}
