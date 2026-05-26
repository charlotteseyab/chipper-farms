<?php

namespace App\Http\Controllers\Finance;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Exceptions\PostingException;
use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\JournalBatch;
use App\Services\Finance\JournalApprovalService;
use App\Services\Finance\JournalReversalService;
use App\Services\Finance\ManualJournalService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Phase 1d — Manual Journal entry, approval queue, and posted journal viewer.
 *
 * Permission gates (route level):
 *   index, show: view_finance
 *   create, store: propose_manual_journal
 *   approve, reject: approve_journal
 *   reverse: reverse_journal
 *   storeAndApprove: approve_own_journals
 *
 * Two-step flow:
 *   - Accountant fills the form, clicks "Propose" → creates draft.
 *   - Admin (or another user with approve_journal) opens the queue,
 *     reviews, clicks "Approve" → posted via JournalApprovalService.
 *   - Admin can reject with a reason.
 *
 * One-step flow (override path):
 *   - User with `approve_own_journals` (admin) sees a "Propose & Approve"
 *     button on the form. Single click posts immediately via
 *     ManualJournalService::proposeAndApprove() → PostingEngine.
 */
class JournalController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();
        $status = $request->get('status', 'all');

        $query = JournalBatch::with(['journals', 'fiscalPeriod', 'proposedBy', 'postedBy'])
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId));

        if (in_array($status, ['draft', 'posted', 'reversed'], true)) {
            $query->where('status', $status);
        }

        $batches = $query->latest('id')->paginate(25)->withQueryString();

        $counts = [
            'draft'    => JournalBatch::where('status', 'draft')
                            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
            'posted'   => JournalBatch::where('status', 'posted')
                            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
            'reversed' => JournalBatch::where('status', 'reversed')
                            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))->count(),
        ];

        return view('finance.journals.index', compact('batches', 'counts', 'status'));
    }

    public function show(JournalBatch $batch, CurrentOrganization $currentOrg)
    {
        $this->ensureBatchInOrg($batch, $currentOrg);

        $batch->load([
            'journals.lines.account',
            'journals.lines.counterparty',
            'fiscalPeriod',
            'proposedBy',
            'postedBy',
            'reversedByUser',
            'reverses',
            'reversedBy',
            'postings',
        ]);

        return view('finance.journals.show', compact('batch'));
    }

    public function create(CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();

        $accounts = Account::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('is_active', true)
            ->where('allow_manual_journal', true)
            ->orderBy('code')
            ->get();

        return view('finance.journals.create', compact('accounts'));
    }

    public function store(Request $request, ManualJournalService $service, CurrentOrganization $currentOrg)
    {
        $validated = $this->validateJournalForm($request);

        try {
            $draft = $this->buildBatchDraftFromRequest($validated);
            $batch = $service->propose($draft, $request->user());

            return redirect()
                ->route('finance.journals.show', $batch)
                ->with('success', 'Manual journal proposed. Awaiting approval.');
        } catch (PostingException | \InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Override path for users with approve_own_journals permission.
     * Posts immediately, no draft.
     */
    public function storeAndApprove(Request $request, ManualJournalService $service, CurrentOrganization $currentOrg)
    {
        $validated = $this->validateJournalForm($request);

        try {
            $draft = $this->buildBatchDraftFromRequest($validated);
            $batch = $service->proposeAndApprove($draft, $request->user());

            return redirect()
                ->route('finance.journals.show', $batch)
                ->with('success', 'Manual journal posted directly.');
        } catch (PostingException | \InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function approve(Request $request, JournalBatch $batch, JournalApprovalService $service, CurrentOrganization $currentOrg)
    {
        $this->ensureBatchInOrg($batch, $currentOrg);

        if ($batch->proposed_by_user_id === $request->user()->id) {
            return back()->with('error', 'You cannot approve a journal you proposed yourself. Ask another user with approval permission.');
        }

        try {
            $service->approve($batch, $request->user());
            return redirect()
                ->route('finance.journals.show', $batch)
                ->with('success', 'Journal approved and posted.');
        } catch (PostingException | \DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, JournalBatch $batch, JournalApprovalService $service, CurrentOrganization $currentOrg)
    {
        $this->ensureBatchInOrg($batch, $currentOrg);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $service->reject($batch, $request->user(), $validated['reason']);
            return redirect()
                ->route('finance.journals.index')
                ->with('success', 'Journal rejected.');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reverse(Request $request, JournalBatch $batch, JournalReversalService $service, CurrentOrganization $currentOrg)
    {
        $this->ensureBatchInOrg($batch, $currentOrg);

        $validated = $request->validate([
            'reason'        => ['required', 'string', 'max:500'],
            'reversal_date' => ['nullable', 'date'],
        ]);

        try {
            $reversalDate = $validated['reversal_date'] ?? null
                ? CarbonImmutable::parse($validated['reversal_date'])
                : CarbonImmutable::now();

            $reversal = $service->reverse(
                original:     $batch,
                reverser:     $request->user(),
                reason:       $validated['reason'],
                reversalDate: $reversalDate,
            );

            return redirect()
                ->route('finance.journals.show', $reversal)
                ->with('success', 'Reversal posted.');
        } catch (PostingException | \DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /* --- helpers --- */

    /**
     * @return array{
     *   posting_date: string,
     *   description: string|null,
     *   reference: string|null,
     *   journal_type: string,
     *   lines: list<array{account_id:int, side:string, amount:string, description:string|null}>
     * }
     */
    protected function validateJournalForm(Request $request): array
    {
        return $request->validate([
            'posting_date'        => ['required', 'date'],
            'description'         => ['nullable', 'string', 'max:500'],
            'reference'           => ['nullable', 'string', 'max:100'],
            'journal_type'        => ['nullable', 'string', 'max:50'],
            'lines'               => ['required', 'array', 'min:2'],
            'lines.*.account_id'  => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.side'        => ['required', 'in:debit,credit'],
            'lines.*.amount'      => ['required', 'numeric', 'min:0.01'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * Convert validated request data into a JournalBatchDraft. Constructor-side
     * invariants (balance, two-or-more lines, one-sided amounts) are enforced
     * by the value object — no need to duplicate them here.
     */
    protected function buildBatchDraftFromRequest(array $validated): JournalBatchDraft
    {
        $lineDrafts = [];
        foreach ($validated['lines'] as $line) {
            $amount = (string) (float) $line['amount'];
            if ($line['side'] === 'debit') {
                $lineDrafts[] = JournalLineDraft::debit(
                    accountId: (int) $line['account_id'],
                    amount: $amount,
                    description: $line['description'] ?? null,
                );
            } else {
                $lineDrafts[] = JournalLineDraft::credit(
                    accountId: (int) $line['account_id'],
                    amount: $amount,
                    description: $line['description'] ?? null,
                );
            }
        }

        $journal = new JournalDraft(
            journalType:  $validated['journal_type'] ?? 'manual',
            lines:        $lineDrafts,
            reference:    $validated['reference'] ?? null,
            description:  $validated['description'] ?? null,
        );

        return new JournalBatchDraft(
            batchType:    'manual_journal',
            postingDate:  CarbonImmutable::parse($validated['posting_date']),
            journals:     [$journal],
            description:  $validated['description'] ?? null,
            sourceModule: 'manual',
        );
    }

    protected function ensureBatchInOrg(JournalBatch $batch, CurrentOrganization $currentOrg): void
    {
        $orgId = $currentOrg->id();
        if ($orgId !== null && $batch->organization_id !== null && (int) $batch->organization_id !== (int) $orgId) {
            abort(403, 'Batch belongs to a different organisation.');
        }
    }
}
