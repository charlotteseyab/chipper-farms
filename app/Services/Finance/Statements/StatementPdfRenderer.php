<?php

namespace App\Services\Finance\Statements;

use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Generates a PDF from a Statement value object.
 *
 * Uses barryvdh/laravel-dompdf (already a project dependency). The view
 * 'finance.statements.pdf' is the rendering template — kept separate
 * from the screen view because dompdf supports a CSS subset (no flexbox,
 * limited grid, no Tailwind utility expansion). The PDF view uses
 * inline styles + tables for layout, in the dompdf-friendly idiom.
 *
 * The renderer is thin on purpose — the heavy lifting is in StatementService.
 * If we ever swap dompdf for wkhtmltopdf or Browsershot, only this class
 * and the pdf view need to change.
 */
class StatementPdfRenderer
{
    /**
     * Render to raw PDF bytes (suitable for Storage::put or response().
     */
    public function render(Statement $statement): string
    {
        $pdf = Pdf::loadView('finance.statements.pdf', [
            'statement' => $statement,
            'company' => [
                'name'    => config('finance.statements.company_name'),
                'address' => config('finance.statements.company_address'),
                'phone'   => config('finance.statements.company_phone'),
                'email'   => config('finance.statements.company_email'),
                'tin'     => config('finance.statements.company_tin'),
            ],
            'footerText' => config('finance.statements.footer_text'),
        ]);

        $pdf->setPaper('a4', 'portrait');

        // dompdf options: enable remote images is OFF for security
        // (we don't load anything external on the statement template).
        $pdf->setOptions([
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'sans-serif',
        ]);

        return $pdf->output();
    }

    /**
     * Stream the PDF to the browser as a download response.
     */
    public function downloadResponse(Statement $statement): \Symfony\Component\HttpFoundation\Response
    {
        $pdfBytes = $this->render($statement);

        return response($pdfBytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $statement->suggestedFilename() . '"',
        ]);
    }

    /**
     * Stream inline (display in browser) instead of forcing download.
     */
    public function inlineResponse(Statement $statement): \Symfony\Component\HttpFoundation\Response
    {
        $pdfBytes = $this->render($statement);

        return response($pdfBytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $statement->suggestedFilename() . '"',
        ]);
    }
}
