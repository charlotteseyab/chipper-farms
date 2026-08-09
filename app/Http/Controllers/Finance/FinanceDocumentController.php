<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Invoice;
use App\Models\Finance\Receipt;
use App\Services\Finance\Documents\CustomerDocumentRenderer;
use App\Support\CurrentOrganization;
use Symfony\Component\HttpFoundation\Response;

/**
 * The customer's copy of an invoice or a receipt (Phase 7, blueprint §28).
 *
 * Kept apart from `InvoiceController` and `ReceiptController` because a
 * customer-facing document is a different concern from the internal register —
 * it has its own rendering rules, its own header source and its own reason to
 * change. Both endpoints do the same two things: prove the record belongs to
 * this organisation, then hand it to the renderer.
 *
 * 404 rather than 403 on a cross-organisation id: another tenant's invoice
 * should not be discoverable by trying numbers (prompt §118). This matters more
 * here than on an internal screen, because a document endpoint is the kind of
 * URL that gets forwarded.
 */
class FinanceDocumentController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected CustomerDocumentRenderer $renderer,
    ) {
    }

    public function invoice(Invoice $invoice): Response
    {
        abort_unless(
            (int) $invoice->organization_id === $this->currentOrganization->requireId(),
            404,
        );

        return $this->renderer->invoice($invoice);
    }

    public function receipt(Receipt $receipt): Response
    {
        abort_unless(
            (int) $receipt->organization_id === $this->currentOrganization->requireId(),
            404,
        );

        return $this->renderer->receipt($receipt);
    }
}
