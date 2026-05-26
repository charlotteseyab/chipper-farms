<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Counterparty;
use App\Models\Finance\SupplierStatement;
use App\Models\Finance\SupplierStatementLine;
use App\Services\Finance\SupplierStatements\StatementReconciliationService;
use App\Services\Finance\SupplierStatements\SupplierStatementService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Supplier statement reconciliation controller.
 *
 * Permission: manage_supplier_statements (introduced Phase 3d).
 *
 * Routes (registered in WEB_PHP_PHASE3D_ROUTES_PATCH.md):
 *   GET    /finance/supplier-statements                index
 *   GET    /finance/supplier-statements/create         create
 *   POST   /finance/supplier-statements                store
 *   GET    /finance/supplier-statements/{statement}    show
 *   POST   /finance/supplier-statements/{statement}/lines               storeLine
 *   POST   /finance/supplier-statements/{statement}/lines/csv           uploadCsv
 *   DELETE /finance/supplier-statements/{statement}/lines/{line}        destroyLine
 *   POST   /finance/supplier-statements/{statement}/lines/{line}/resolve resolveManually
 *   POST   /finance/supplier-statements/{statement}/reconcile            reconcile
 *   POST   /finance/supplier-statements/{statement}/flag                 markFlagged
 *   POST   /finance/supplier-statements/{statement}/reconciled           markReconciled
 *   POST   /finance/supplier-statements/{statement}/archive              archive
 */
class SupplierStatementController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg)
    {
        $this->ensureViewPermission($request);
        $orgId = $currentOrg->id();
        $status     = $request->get('status', 'all');
        $supplierId = $request->get('supplier_id');

        $query = SupplierStatement::with(['counterparty:id,name,code'])
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->latest('id');

        if (in_array($status, SupplierStatement::ALL_STATUSES, true)) {
            $query->where('status', $status);
        } elseif ($status === 'open') {
            $query->open();
        }

        if ($supplierId) {
            $query->where('counterparty_id', $supplierId);
        }

        $statements = $query->paginate(25)->withQueryString();

        $counts = [
            'total'      => SupplierStatement::query()
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
            'draft'      => SupplierStatement::query()
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->where('status', SupplierStatement::STATUS_DRAFT)->count(),
            'in_progress'=> SupplierStatement::query()
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->where('status', SupplierStatement::STATUS_IN_PROGRESS)->count(),
            'flagged'    => SupplierStatement::query()
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->where('status', SupplierStatement::STATUS_FLAGGED)->count(),
            'reconciled' => SupplierStatement::query()
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->where('status', SupplierStatement::STATUS_RECONCILED)->count(),
        ];

        $suppliers = Counterparty::suppliers()->active()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('finance.supplier-statements.index', compact(
            'statements', 'counts', 'status', 'supplierId', 'suppliers'
        ));
    }

    public function create(Request $request, CurrentOrganization $currentOrg)
    {
        $this->ensureManagePermission($request);
        $orgId = $currentOrg->id();
        $suppliers = Counterparty::suppliers()->active()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'currency_code', 'tax_id']);

        $preselectId = $request->integer('counterparty_id') ?: null;

        return view('finance.supplier-statements.create', compact('suppliers', 'preselectId'));
    }

    public function store(
        Request $request,
        SupplierStatementService $service,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureManagePermission($request);
        $data = $request->validate([
            'counterparty_id'    => ['required', 'integer', 'exists:counterparties,id'],
            'statement_date'     => ['required', 'date'],
            'period_from'        => ['required', 'date'],
            'period_to'          => ['required', 'date', 'after_or_equal:period_from'],
            'currency_code'      => ['nullable', 'string', 'size:3'],
            'supplier_balance'   => ['required', 'numeric'],
            'supplier_reference' => ['nullable', 'string', 'max:100'],
            'notes'              => ['nullable', 'string', 'max:5000'],
        ]);

        $supplier = Counterparty::findOrFail($data['counterparty_id']);
        $this->ensureSupplierInOrg($supplier, $currentOrg);

        try {
            $statement = $service->createDraft(
                supplier:        $supplier,
                statementDate:   new \DateTimeImmutable($data['statement_date']),
                periodFrom:      new \DateTimeImmutable($data['period_from']),
                periodTo:        new \DateTimeImmutable($data['period_to']),
                supplierBalance: (string) $data['supplier_balance'],
                createdBy:       $request->user(),
                extra: [
                    'currency_code'      => $data['currency_code'] ?? null,
                    'supplier_reference' => $data['supplier_reference'] ?? null,
                    'notes'              => $data['notes'] ?? null,
                ],
            );
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('finance.supplier-statements.show', $statement)
            ->with('success', "Statement {$statement->statement_number} created.");
    }

    public function show(SupplierStatement $statement, CurrentOrganization $currentOrg, StatementReconciliationService $matcher)
    {
        $this->ensureViewPermission();
        $this->ensureInOrg($statement, $currentOrg);

        $statement->load([
            'counterparty:id,name,code,currency_code,tax_id',
            'lines',
            'createdBy:id,name',
            'lastReconciledBy:id,name',
            'archivedBy:id,name',
        ]);

        // Sidebar: AP detail not currently appearing on the statement.
        $missingFromStatement = $matcher->missingFromStatement($statement);

        return view('finance.supplier-statements.show', compact('statement', 'missingFromStatement'));
    }

    public function storeLine(
        Request $request,
        SupplierStatement $statement,
        SupplierStatementService $service,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureManagePermission($request);
        $this->ensureInOrg($statement, $currentOrg);

        $data = $request->validate([
            'document_date'       => ['required', 'date'],
            'document_kind'       => ['required', Rule::in(SupplierStatementLine::ALL_KINDS)],
            'document_reference'  => ['required', 'string', 'max:100'],
            'amount'              => ['required', 'numeric'],
            'description'         => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $service->addLine($statement, $data);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Line added.');
    }

    public function uploadCsv(
        Request $request,
        SupplierStatement $statement,
        SupplierStatementService $service,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureManagePermission($request);
        $this->ensureInOrg($statement, $currentOrg);

        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $rows = [];
        $f = fopen($request->file('csv_file')->getRealPath(), 'r');
        if ($f === false) {
            return back()->with('error', 'Could not read uploaded file.');
        }

        try {
            $headers = fgetcsv($f);
            if ($headers === false) {
                return back()->with('error', 'CSV is empty.');
            }
            $headers = array_map(fn ($h) => trim((string) $h), $headers);

            $required = ['document_date', 'document_kind', 'document_reference', 'amount'];
            foreach ($required as $r) {
                if (! in_array($r, $headers, true)) {
                    return back()->with('error',
                        "CSV missing required column: '{$r}'. Required columns: "
                        . implode(', ', $required) . ' (description optional).');
                }
            }

            while (($row = fgetcsv($f)) !== false) {
                if (count(array_filter($row, fn ($v) => $v !== '' && $v !== null)) === 0) {
                    continue;
                }
                $rows[] = array_combine($headers, array_pad($row, count($headers), null));
            }
        } finally {
            fclose($f);
        }

        try {
            $result = $service->addLinesFromCsv($statement, $rows);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Imported {$result['added']} line(s).");
    }

    public function destroyLine(
        SupplierStatement $statement,
        SupplierStatementLine $line,
        SupplierStatementService $service,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureManagePermission();
        $this->ensureInOrg($statement, $currentOrg);

        if ((int) $line->supplier_statement_id !== (int) $statement->id) {
            abort(404);
        }

        try {
            $service->removeLine($line);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Line removed.');
    }

    public function resolveManually(
        Request $request,
        SupplierStatement $statement,
        SupplierStatementLine $line,
        StatementReconciliationService $matcher,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureManagePermission($request);
        $this->ensureInOrg($statement, $currentOrg);

        if ((int) $line->supplier_statement_id !== (int) $statement->id) {
            abort(404);
        }

        $data = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $matcher->markLineManuallyResolved($line, $request->user(), $data['note']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Line marked manually resolved.');
    }

    public function reconcile(
        Request $request,
        SupplierStatement $statement,
        StatementReconciliationService $matcher,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureManagePermission($request);
        $this->ensureInOrg($statement, $currentOrg);

        try {
            $summary = $matcher->reconcile($statement, $request->user());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = sprintf(
            'Reconciled. Matched: %d, amount mismatch: %d, not in our books: %d, duplicates: %d. Our balance: %s, difference: %s.',
            $summary['matched'],
            $summary['amount_mismatch'],
            $summary['not_in_our_books'],
            $summary['duplicate_match'],
            $summary['our_balance'],
            $summary['balance_difference'],
        );
        return back()->with('success', $msg);
    }

    public function markFlagged(
        Request $request,
        SupplierStatement $statement,
        SupplierStatementService $service,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureManagePermission($request);
        $this->ensureInOrg($statement, $currentOrg);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        try {
            $service->markFlagged($statement, $request->user(), $data['reason']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Statement flagged.');
    }

    public function markReconciled(
        Request $request,
        SupplierStatement $statement,
        SupplierStatementService $service,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureManagePermission($request);
        $this->ensureInOrg($statement, $currentOrg);

        try {
            $service->markReconciled($statement, $request->user());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Statement marked reconciled.');
    }

    public function archive(
        Request $request,
        SupplierStatement $statement,
        SupplierStatementService $service,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureManagePermission($request);
        $this->ensureInOrg($statement, $currentOrg);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        try {
            $service->archive($statement, $request->user(), $data['reason']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect()
            ->route('finance.supplier-statements.index')
            ->with('success', 'Statement archived.');
    }

    /* internals */

    protected function ensureInOrg(SupplierStatement $statement, CurrentOrganization $currentOrg): void
    {
        $orgId = $currentOrg->id();
        if ($orgId !== null && $statement->organization_id !== null
            && (int) $statement->organization_id !== (int) $orgId) {
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
        if (! $user?->hasPermission('manage_supplier_statements')) {
            abort(403);
        }
    }

    protected function ensureViewPermission(?Request $request = null): void
    {
        $user = $request?->user() ?? auth()->user();
        if (
            ! $user?->hasPermission('view_finance')
            && ! $user?->hasPermission('manage_supplier_statements')
        ) {
            abort(403);
        }
    }
}
