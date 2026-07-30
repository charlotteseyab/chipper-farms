<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinanceAttachment;
use App\Models\Finance\Invoice;
use App\Models\Finance\Receipt;
use App\Models\Finance\Refund;
use App\Models\Finance\VendorInvoice;
use App\Models\Finance\Payment;
use App\Services\Finance\Attachments\AttachmentService;
use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Finance attachment controller.
 *
 * Polymorphic attachments on Receipt, Invoice, Refund, VendorInvoice, Payment.
 *
 * Authorisation model (remediation Phase 2 — CF-P0-004, CF-P1-019)
 * ----------------------------------------------------------------
 * PARENT_MAP below is the single source of truth for which parent types are
 * supported and what permission each action requires. Routes, upload, download
 * and delete all derive from it, so the three can no longer drift apart.
 *
 *   download: the parent's `view` permission OR its `write` permission. A user
 *             who may create or amend a record may see its own evidence, which
 *             keeps roles such as farmmanager (create_purchase_request_vendor_invoice
 *             but no view_finance) working without granting them the module.
 *   upload / delete: the parent's `write` permission.
 *
 * Defects this replaced:
 *   - CF-P0-004: download() checked organisation and file existence only. It
 *     never checked view_finance, despite this class comment previously
 *     claiming "download: view_finance". Any same-organisation user with an
 *     attachment id could stream payment, receipt, invoice and refund evidence.
 *   - CF-P1-019: three sources disagreed — the route regex allowed
 *     receipts|invoices|refunds, resolveParent() also accepted vendor-invoices
 *     and payments, and authorizeWriteFor() had no Payment mapping, so Payment
 *     attachments were unreachable in one direction and unwritable in the other.
 *   - ensureInOrg() failed OPEN in two ways (not in the original register):
 *     it returned early when the current organisation could not be resolved,
 *     and it passed any parent whose organization_id was null. Both now deny.
 *
 * The download route serves attachments stored on disks that don't
 * support signed URLs (e.g. local disk in dev). For production S3-style
 * disks, the FinanceAttachment::downloadUrl() returns a temporary signed
 * URL that bypasses Laravel entirely.
 */
class AttachmentController extends Controller
{
    /**
     * Supported attachment parents and their permissions.
     *
     * Keyed by the URL segment used in the route. `class` is the parent model,
     * `view` gates reading the evidence, `write` gates attaching or removing it.
     *
     * @var array<string, array{class: class-string<Model>, view: string, write: string}>
     */
    protected const PARENT_MAP = [
        'receipts' => [
            'class' => Receipt::class,
            'view'  => 'view_finance',
            'write' => 'create_receipt',
        ],
        'invoices' => [
            'class' => Invoice::class,
            'view'  => 'view_finance',
            'write' => 'create_invoice',
        ],
        'refunds' => [
            'class' => Refund::class,
            'view'  => 'view_finance',
            'write' => 'create_refund',
        ],
        'vendor-invoices' => [
            'class' => VendorInvoice::class,
            'view'  => 'view_finance',
            'write' => 'create_vendor_invoice',
        ],
        'payments' => [
            'class' => Payment::class,
            'view'  => 'view_finance',
            'write' => 'create_payment',
        ],
    ];

    /**
     * Route constraint for the {type} segment, derived from PARENT_MAP so the
     * route and the resolver cannot disagree (CF-P1-019).
     */
    public static function supportedTypePattern(): string
    {
        return implode('|', array_keys(static::PARENT_MAP));
    }
    public function store(
        Request $request,
        string $type,
        int $id,
        AttachmentService $service,
        CurrentOrganization $currentOrg,
    ) {
        $parent = $this->resolveParent($type, $id, $currentOrg);
        $this->authorizeWriteFor($request, $parent);

        $maxBytes = ((int) config('finance.attachments.max_size_mb', 20)) * 1024 * 1024;
        $allowedMimes = config('finance.attachments.allowed_mime_types', []);

        $validated = $request->validate([
            'file'        => ['required', 'file', 'max:' . ((int) ($maxBytes / 1024))],
            'purpose'     => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        // Mime check (Laravel's mimes: rule fights with custom types; verify here).
        $mime = $request->file('file')->getMimeType();
        if (! in_array($mime, $allowedMimes, true)) {
            return back()->with('error', "File type '{$mime}' not allowed.");
        }

        try {
            $service->upload(
                attachable:   $parent,
                file:         $request->file('file'),
                uploadedBy:   $request->user(),
                purpose:      $validated['purpose'] ?? null,
                description:  $validated['description'] ?? null,
            );
        } catch (\DomainException | \RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'File uploaded.');
    }

    public function download(Request $request, FinanceAttachment $attachment, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($attachment, $currentOrg);

        // CF-P0-004: the download must be authorised against the parent record,
        // not merely scoped to the organisation. Resolving the parent also
        // re-checks its organisation, so an attachment cannot be used as a
        // side channel to a record in another tenant.
        $this->authorizeReadFor($request, $attachment);

        if (! $attachment->exists()) {
            abort(404, 'Attachment file not found on disk.');
        }

        $stream = $attachment->streamContents();
        if ($stream === false || $stream === null) {
            abort(500, 'Cannot read attachment.');
        }

        return new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type'        => $attachment->mime_type,
            'Content-Disposition' => 'attachment; filename="' . addslashes($attachment->original_name) . '"',
            'Content-Length'      => $attachment->size_bytes,
        ]);
    }

    public function destroy(
        Request $request,
        FinanceAttachment $attachment,
        AttachmentService $service,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureInOrg($attachment, $currentOrg);
        $parent = $attachment->attachable;
        if ($parent) {
            $this->authorizeWriteFor($request, $parent);
        }

        $service->delete($attachment);

        return back()->with('success', 'Attachment removed.');
    }

    /* internals */

    protected function resolveParent(string $type, int $id, CurrentOrganization $currentOrg): Model
    {
        $config = static::PARENT_MAP[$type]
            ?? abort(404, "Unsupported attachment parent type: {$type}");

        /** @var class-string<Model> $class */
        $class  = $config['class'];
        $parent = $class::findOrFail($id);
        $this->ensureInOrg($parent, $currentOrg);

        return $parent;
    }

    /**
     * Organisation guard. Fails CLOSED in every ambiguous case.
     *
     * Previously this returned early when the current organisation could not be
     * resolved, and passed any record whose organization_id was null — so an
     * unscoped request or a legacy unbacked row bypassed tenancy entirely.
     * Finance evidence is exactly the wrong place to be permissive, so both
     * cases now deny.
     */
    protected function ensureInOrg(Model $parent, CurrentOrganization $currentOrg): void
    {
        $orgId = $currentOrg->resolve()?->id;

        if ($orgId === null) {
            abort(403, 'No organization context for this request.');
        }

        $parentOrgId = $parent->organization_id ?? null;

        if ($parentOrgId === null) {
            abort(403, 'This record has no organization and cannot be accessed.');
        }

        if ((int) $parentOrgId !== (int) $orgId) {
            abort(403);
        }
    }

    /**
     * Per-parent read permission for streaming the evidence file.
     *
     * Accepts the parent's `view` permission OR its `write` permission: a user
     * entitled to create or amend the record is entitled to see the document
     * supporting it. This keeps narrowly-permissioned operational roles working
     * without granting them blanket finance visibility.
     */
    protected function authorizeReadFor(Request $request, FinanceAttachment $attachment): void
    {
        $parent = $attachment->attachable;

        // An attachment whose parent has been removed, or whose parent type is
        // not in PARENT_MAP, cannot be authorised — deny rather than stream it.
        if (! $parent instanceof Model) {
            abort(403, 'This attachment has no resolvable parent record.');
        }

        $config = $this->configForParent($parent);

        if ($config === null) {
            abort(403, 'This attachment parent type is not supported.');
        }

        $user = $request->user();

        if (! $user?->hasAnyPermission([$config['view'], $config['write']])) {
            abort(403, 'You do not have permission to download this document.');
        }

        // The attachment row and its parent must both belong to the caller's
        // organisation; a mismatch means the polymorphic link crosses tenants.
        $this->ensureInOrg($parent, app(CurrentOrganization::class));
    }

    /**
     * Per-parent write permission: a user uploading to a Receipt needs
     * create_receipt; uploading to an Invoice needs create_invoice; etc.
     *
     * Payment was previously absent from this map, so Payment attachments hit
     * the null branch and were permanently unwritable (CF-P1-019).
     */
    protected function authorizeWriteFor(Request $request, Model $parent): void
    {
        $config = $this->configForParent($parent);
        $user   = $request->user();

        if ($config === null || ! $user?->hasPermission($config['write'])) {
            abort(403, "You do not have permission to attach files to this {$parent->getTable()} record.");
        }
    }

    /**
     * Reverse lookup from a parent model instance to its PARENT_MAP entry.
     *
     * @return array{class: class-string<Model>, view: string, write: string}|null
     */
    protected function configForParent(Model $parent): ?array
    {
        foreach (static::PARENT_MAP as $config) {
            if ($parent instanceof $config['class']) {
                return $config;
            }
        }

        return null;
    }
}
