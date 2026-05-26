<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\BankAccount;
use App\Models\Finance\BankStatement;
use App\Models\Finance\BankStatementLine;
use App\Services\Finance\BankReconciliation\BankStatementMatchingService;
use App\Services\Finance\BankStatements\BankStatementImportService;
use App\Services\Finance\BankStatements\Parsers\BankStatementParserRegistry;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Bank statement import + match — Phase 3e.
 *
 * Permission gate: import_bank_statement (covers all routes here).
 * Reconcile-time actions are gated separately on
 * BankReconciliationController.
 */
class BankStatementController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg)
    {
        $this->ensureImportPermission($request);
        $orgId = $currentOrg->id();
        $bankAccountId = $request->get('bank_account_id');
        $statusFilter  = $request->get('status', 'all');
        $periodFilter  = $request->get('period');  // 'YYYY-MM' or null

        $q = BankStatement::query()
            ->with(['bankAccount.account', 'importedBy'])
            ->where('organization_id', $orgId)
            ->orderByDesc('period_to')
            ->orderByDesc('id');

        if ($bankAccountId) {
            $q->where('bank_account_id', $bankAccountId);
        }
        if ($statusFilter !== 'all'
            && in_array($statusFilter, BankStatement::ALL_STATUSES, true)) {
            $q->where('status', $statusFilter);
        }
        if ($periodFilter && preg_match('/^\d{4}-\d{2}$/', $periodFilter)) {
            $q->where('period_to', '>=', $periodFilter . '-01')
              ->where('period_from', '<=', $periodFilter . '-31');
        }

        $statements = $q->paginate(50)->withQueryString();

        $bankAccounts = BankAccount::active()
            ->where('organization_id', $orgId)
            ->orderBy('label')
            ->get();

        return view('finance.bank-statements.index', [
            'statements'    => $statements,
            'bankAccounts'  => $bankAccounts,
            'bankAccountId' => $bankAccountId,
            'statusFilter'  => $statusFilter,
            'periodFilter'  => $periodFilter,
            'allStatuses'   => BankStatement::ALL_STATUSES,
        ]);
    }

    public function create(Request $request, CurrentOrganization $currentOrg, BankStatementParserRegistry $registry)
    {
        $this->ensureImportPermission($request);
        $orgId = $currentOrg->id();
        $bankAccountId = $request->get('bank_account_id');

        $bankAccounts = BankAccount::active()
            ->where('organization_id', $orgId)
            ->orderBy('label')
            ->get();

        $bankAccount = $bankAccountId
            ? $bankAccounts->firstWhere('id', (int) $bankAccountId)
            : null;

        return view('finance.bank-statements.create', [
            'bankAccounts'  => $bankAccounts,
            'bankAccount'   => $bankAccount,
            'parsers'       => $registry->picker(),
        ]);
    }

    public function store(
        Request $request,
        CurrentOrganization $currentOrg,
        BankStatementImportService $importer,
    ) {
        $this->ensureImportPermission($request);
        $orgId = $currentOrg->id();
        $data = $request->validate([
            'bank_account_id'              => ['required', 'integer',
                Rule::exists('bank_accounts', 'id')->where(fn ($q) => $q->where('organization_id', $orgId))],
            'file'                         => ['required', 'file', 'max:20480'], // 20 MB
            'source_format'                => ['nullable', 'string', 'max:50'],
            'period_from'                  => ['nullable', 'date'],
            'period_to'                    => ['nullable', 'date', 'after_or_equal:period_from'],
            'opening_balance_per_bank'     => ['nullable', 'numeric'],
            'closing_balance_per_bank'     => ['nullable', 'numeric'],
            'currency_code'                => ['nullable', 'string', 'size:3'],
            'notes'                        => ['nullable', 'string'],
        ]);

        $bankAccount = BankAccount::query()
            ->where('organization_id', $orgId)
            ->findOrFail($data['bank_account_id']);

        $uploaded = $request->file('file');
        $contents = file_get_contents($uploaded->getRealPath());
        if ($contents === false || $contents === '') {
            return back()->withInput()->with('error', 'Could not read uploaded file.');
        }
        $originalName = $uploaded->getClientOriginalName() ?: 'statement.csv';

        try {
            $statement = $importer->import(
                $bankAccount,
                $contents,
                $originalName,
                [
                    'source_format'            => $data['source_format'] ?? null,
                    'period_from'              => $data['period_from']   ?? null,
                    'period_to'                => $data['period_to']     ?? null,
                    'opening_balance_per_bank' => isset($data['opening_balance_per_bank'])
                        ? (string) $data['opening_balance_per_bank'] : null,
                    'closing_balance_per_bank' => isset($data['closing_balance_per_bank'])
                        ? (string) $data['closing_balance_per_bank'] : null,
                    'currency_code'            => $data['currency_code'] ?? null,
                    'notes'                    => $data['notes']         ?? null,
                ],
                $request->user(),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', 'Parse failed: ' . $e->getMessage());
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Import failed: ' . $e->getMessage());
        }

        return redirect()
            ->route('finance.bank-statements.show', $statement)
            ->with('success', "Imported {$statement->statement_number} ({$statement->line_count} lines).");
    }

    public function show(BankStatement $bankStatement, CurrentOrganization $currentOrg)
    {
        $this->ensureImportPermission();
        $this->ensureInOrg($bankStatement, $currentOrg);

        $bankStatement->load(['bankAccount.account', 'importedBy', 'voidedBy', 'reconciliation']);
        $lines = $bankStatement->lines()
            ->orderBy('line_number')
            ->get();
        $counts = $bankStatement->matchCountsByStatus();

        return view('finance.bank-statements.show', [
            'statement' => $bankStatement,
            'lines'     => $lines,
            'counts'    => $counts,
        ]);
    }

    public function runMatch(
        BankStatement $bankStatement,
        Request $request,
        CurrentOrganization $currentOrg,
        BankStatementMatchingService $matcher,
    ) {
        $this->ensureImportPermission($request);
        $this->ensureInOrg($bankStatement, $currentOrg);

        try {
            $stats = $matcher->autoMatch($bankStatement, $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = sprintf(
            'Matched %d, %d duplicates, %d unmatched. (%d skipped already-matched, %d skipped resolved.)',
            $stats['matched'],
            $stats['duplicates'],
            $stats['unmatched'],
            $stats['skipped_already_matched'],
            $stats['skipped_resolved'],
        );
        return back()->with('success', $msg);
    }

    public function manualMatch(
        BankStatementLine $bankStatementLine,
        Request $request,
        CurrentOrganization $currentOrg,
        BankStatementMatchingService $matcher,
    ) {
        $this->ensureImportPermission($request);
        $this->ensureLineInOrg($bankStatementLine, $currentOrg);

        $data = $request->validate([
            'matched_type' => ['required', Rule::in(BankStatementLine::ALL_MATCHED_TYPES)],
            'matched_id'   => ['required', 'integer', 'min:1'],
        ]);

        try {
            $matcher->manualMatch(
                $bankStatementLine,
                $data['matched_type'],
                (int) $data['matched_id'],
                $request->user(),
            );
        } catch (\DomainException | \InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Manual match recorded.');
    }

    public function unmatch(
        BankStatementLine $bankStatementLine,
        Request $request,
        CurrentOrganization $currentOrg,
        BankStatementMatchingService $matcher,
    ) {
        $this->ensureImportPermission($request);
        $this->ensureLineInOrg($bankStatementLine, $currentOrg);

        try {
            $matcher->unmatch($bankStatementLine, $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Match removed.');
    }

    public function markResolve(
        BankStatementLine $bankStatementLine,
        Request $request,
        CurrentOrganization $currentOrg,
        BankStatementMatchingService $matcher,
    ) {
        $this->ensureImportPermission($request);
        $this->ensureLineInOrg($bankStatementLine, $currentOrg);

        $data = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $matcher->markManuallyResolved(
                $bankStatementLine,
                $request->user(),
                $data['note'],
            );
        } catch (\DomainException | \InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Line marked as manually resolved.');
    }

    public function voidStatement(
        BankStatement $bankStatement,
        Request $request,
        CurrentOrganization $currentOrg,
        BankStatementImportService $importer,
    ) {
        $this->ensureImportPermission($request);
        $this->ensureInOrg($bankStatement, $currentOrg);

        $data = $request->validate([
            'void_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        try {
            $importer->void($bankStatement, $request->user(), $data['void_reason']);
        } catch (\DomainException | \InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Statement voided.');
    }

    public function downloadFile(BankStatement $bankStatement, CurrentOrganization $currentOrg)
    {
        $this->ensureImportPermission();
        $this->ensureInOrg($bankStatement, $currentOrg);
        if (! $bankStatement->file_disk || ! $bankStatement->file_path) {
            abort(404);
        }
        $disk = Storage::disk($bankStatement->file_disk);
        if (! $disk->exists($bankStatement->file_path)) {
            abort(404);
        }
        return $disk->download(
            $bankStatement->file_path,
            $bankStatement->original_filename ?: 'statement.csv',
        );
    }

    protected function ensureInOrg(BankStatement $stmt, CurrentOrganization $currentOrg): void
    {
        $orgId = $currentOrg->id();
        if ($stmt->organization_id !== $orgId) abort(404);
    }

    protected function ensureLineInOrg(BankStatementLine $line, CurrentOrganization $currentOrg): void
    {
        $orgId = $currentOrg->id();
        if ($line->organization_id !== $orgId) abort(404);
    }

    protected function ensureImportPermission(?Request $request = null): void
    {
        $user = $request?->user() ?? auth()->user();
        if (! $user?->hasPermission('import_bank_statement')) {
            abort(403, 'Missing permission: import_bank_statement');
        }
    }
}
