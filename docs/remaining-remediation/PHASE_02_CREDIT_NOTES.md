# Phase 02 - Credit Notes

Implemented a document-led sales-return revenue reversal flow:

- `credit_notes` and `credit_note_lines` tables.
- `App\Models\Finance\CreditNote` and `CreditNoteLine`.
- `App\Services\Finance\CreditNotes\CreditNoteService`.
- `SalesReturnRevenueSubscriber` now creates/posts an `auto_credit_note` batch.

Key controls:

- one credit note per sales return via idempotency key;
- original revenue must be posted before a credit note is created;
- cash sales credit cash, credit sales credit accounts receivable;
- partial returns calculate proportional revenue;
- replayed return events do not create duplicate credit notes or postings.

Evidence:

- `php artisan test tests/Feature/Finance/SalesReturns/SalesReturnRevenueTest.php`: 6 passed / 24 assertions.
- MariaDB targeted run: 17 passed / 62 assertions across sales-return and webhook tests.
