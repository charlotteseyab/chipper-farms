# Phase 02 - Skip Guard Remediation

Removed obsolete conditional skip guards that referred to old `WEB_PHP_*` or payment-allocation patches where the functionality now exists.

Changed affected tests to hard assertions for required route/service surfaces:

- Customers, invoices, receipts, refunds, vendor invoices, payment runs, payments, supplier statements, WHT certificates, AP/AR ageing, bank reconciliation, advanced feature gating.
- WHT and bank reconciliation payment-allocation dependencies.
- COA remediation migration file presence.

Verification:

- Focused finance slice after cache cleanup: 146 passed, 433 assertions.
