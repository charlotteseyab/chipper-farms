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
 * Polymorphic uploads to Receipt, Invoice, Refund.
 *
 * Permissions:
 *   upload, delete:    create_invoice OR create_receipt OR create_refund
 *                      (whichever fits the parent — checked per-parent below)
 *   download:          view_finance
 *
 * The download route serves attachments stored on disks that don't
 * support signed URLs (e.g. local disk in dev). For production S3-style
 * disks, the FinanceAttachment::downloadUrl() returns a temporary signed
 * URL that bypasses Laravel entirely.
 */
class AttachmentController extends Controller
{
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

    public function download(FinanceAttachment $attachment, CurrentOrganization $currentOrg)
    {
        $this->ensureInOrg($attachment, $currentOrg);

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
        $class = match ($type) {
            'receipts' => Receipt::class,
            'invoices' => Invoice::class,
            'refunds'  => Refund::class,
            'vendor-invoices' => VendorInvoice::class,
            'payments'        => Payment::class,
            default    => abort(404, "Unsupported attachment parent type: {$type}"),
        };

        $parent = $class::findOrFail($id);
        $this->ensureInOrg($parent, $currentOrg);
        return $parent;
    }

    protected function ensureInOrg(Model $parent, CurrentOrganization $currentOrg): void
    {
        $orgId = $currentOrg->resolve()?->id;
        if ($orgId === null) return;
        $parentOrgId = $parent->organization_id ?? null;
        if ($parentOrgId !== null && (int) $parentOrgId !== (int) $orgId) {
            abort(403);
        }
    }

    /**
     * Per-parent write permission: a user uploading to a Receipt needs
     * create_receipt; uploading to an Invoice needs create_invoice; etc.
     */
    protected function authorizeWriteFor(Request $request, Model $parent): void
    {
        $user = $request->user();
        $perm = match (get_class($parent)) {
            Receipt::class => 'create_receipt',
            Invoice::class => 'create_invoice',
            Refund::class  => 'create_refund',
            VendorInvoice::class => 'create_vendor_invoice',
            default        => null,
        };

        if (! $perm || ! $user?->hasPermission($perm)) {
            abort(403, "You do not have permission to attach files to this {$parent->getTable()} record.");
        }
    }
}
