<?php

namespace App\Services\Finance\Documents;

use App\Models\Finance\CreditNote;
use App\Models\Finance\Invoice;
use App\Models\Finance\Receipt;
use App\Models\Organization;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Customer-facing documents — invoice, receipt, credit note (Phase 7,
 * blueprint §28 "customer-facing printable receipt", §29 "customer-facing
 * document").
 *
 * WHY THIS EXISTS
 * ---------------
 * Before Phase 7 the only PDF a customer could be handed was a WHT certificate
 * and an account statement. A farm could raise an invoice, take the money and
 * issue a credit note, and had nothing to give the person on the other side of
 * any of it. §28 and §29 both name the document as a requirement, and §29's
 * reason is the sharper one: a credit note without a customer-facing document
 * is a number in a ledger the customer has to take on trust.
 *
 * THE HEADER IS THE ORGANISATION'S, NOT THE ENVIRONMENT'S
 * -------------------------------------------------------
 * `StatementPdfRenderer` reads its company block from
 * `config('finance.statements.company_*')`, which is environment configuration
 * — one company name for the whole installation. That is the `system_configs`
 * defect (IMP-001) wearing a different hat: on a second tenant, one farm's
 * invoices would carry another farm's name and TIN.
 *
 * These documents read the `organizations` row instead, which is per-tenant and
 * already carries name, legal name, address, phone, email and tax identifier.
 * The env values remain the fallback for anything the organisation has not
 * filled in, so a single-tenant installation configured the old way still
 * prints the same header.
 *
 * DOMPDF, LIKE THE STATEMENT
 * --------------------------
 * Same library and the same idiom: tables and inline styles, not Tailwind —
 * dompdf supports a CSS subset with no flexbox and no utility expansion. If the
 * renderer is ever swapped, only this class and the three views change.
 */
class CustomerDocumentRenderer
{
    public function invoice(Invoice $invoice): Response
    {
        $invoice->loadMissing(['lines', 'counterparty']);

        return $this->stream(
            'finance.pdf.invoice',
            ['invoice' => $invoice],
            $this->filename('invoice', $invoice->invoice_number, $invoice->id),
            $invoice->organization_id,
        );
    }

    public function receipt(Receipt $receipt): Response
    {
        $receipt->loadMissing(['counterparty', 'paymentMethod', 'activeAllocations.invoice']);

        return $this->stream(
            'finance.pdf.receipt',
            ['receipt' => $receipt],
            $this->filename('receipt', $receipt->receipt_number, $receipt->id),
            $receipt->organization_id,
        );
    }

    public function creditNote(CreditNote $creditNote): Response
    {
        $creditNote->loadMissing(['lines', 'customer', 'originalInvoice']);

        return $this->stream(
            'finance.pdf.credit-note',
            ['creditNote' => $creditNote],
            $this->filename('credit-note', $creditNote->credit_note_number, $creditNote->id),
            $creditNote->organization_id,
        );
    }

    /**
     * The farm's own details for the document header.
     *
     * @return array<string, ?string>
     */
    public function issuer(?int $organizationId): array
    {
        $organization = $organizationId === null ? null : Organization::find($organizationId);

        return [
            'name'    => $organization?->legal_name ?: $organization?->name
                ?: (string) config('finance.statements.company_name'),
            'address' => $organization?->address ?: config('finance.statements.company_address'),
            'phone'   => $organization?->phone ?: config('finance.statements.company_phone'),
            'email'   => $organization?->email ?: config('finance.statements.company_email'),
            'tin'     => $organization?->tax_identifier ?: config('finance.statements.company_tin'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function stream(string $view, array $data, string $filename, ?int $organizationId): Response
    {
        $pdf = Pdf::loadView($view, $data + ['issuer' => $this->issuer($organizationId)]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            // Remote assets stay off. These documents load nothing external,
            // and a customer-facing template that fetched a URL would be a
            // server-side request driven by document content.
            'isRemoteEnabled'      => false,
            'isHtml5ParserEnabled' => true,
            'defaultFont'          => 'sans-serif',
        ]);

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            // Inline: the common act is showing it to the person standing
            // there, or printing it. A forced download makes that two steps
            // on a phone.
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    protected function filename(string $kind, ?string $number, int $id): string
    {
        $stem = $number !== null && trim($number) !== ''
            ? preg_replace('/[^A-Za-z0-9_-]/', '-', $number)
            : "{$kind}-{$id}";

        return "{$stem}.pdf";
    }
}
