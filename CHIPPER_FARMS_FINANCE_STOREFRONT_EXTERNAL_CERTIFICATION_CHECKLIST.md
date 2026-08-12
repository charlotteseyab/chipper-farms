# Chipper Farms Finance + Storefront External Certification Checklist

This checklist is for external certification. It is not a production GO.

## Required Before Production GO

- Confirm staging branch contains the final remediation commit.
- Run the full default suite on staging.
- Run the guarded production-cache gate on staging.
- Run the guarded MariaDB full suite on staging.
- Run the guarded concurrency suite on staging.
- Run browser/Dusk mobile viewport checks against `chipper_farms_upgrade_test`.
- Execute Paystack sandbox charge, callback, duplicate webhook, refund submission, refund settlement/failure webhook, and reconciliation checks with real sandbox credentials.
- Verify queue worker and scheduler under the actual process manager.
- Verify storage permissions, attachment upload/download, and signed URL behavior in deployed storage.
- Verify backups and restore drill against `chipper_farms_restore_test`.
- Review `.env` secrets and confirm no production secret appears in repository files or rendered pages.

## Current Local Certification Snapshot

- Default suite: passed.
- Production-cache MariaDB gate: passed.
- Node audit/build: passed.
- Full explicit MariaDB gate: passed.
- Concurrency gate: passed.
- Browser/Dusk gate: passed locally against `chipper_farms_upgrade_test`.
- Paystack sandbox: external pending.
