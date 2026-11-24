<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;

/**
 * PDF exports are rendered in the web request, and rendering slows sharply
 * with length: a few thousand rows hold a web worker for minutes and can run
 * out of memory, and a few people exporting at once can take the site down.
 * Longer exports go to Excel/CSV, which streams.
 */
final class PdfExportLimit
{
    public const MAX_ROWS = 1000;

    /** A redirect back with a message when the export is too long; null when it is fine. */
    public static function check(int $rows): ?RedirectResponse
    {
        if ($rows <= self::MAX_ROWS) {
            return null;
        }

        return back()->withErrors(['export' => 'This PDF would have ' . number_format($rows) . ' rows; a PDF can hold at most '
            . number_format(self::MAX_ROWS) . '. Narrow it with the filters, or export to Excel or CSV instead.']);
    }
}
