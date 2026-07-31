# Phase 07 - Backup And Restore

No production backup or restore was executed in this local remediation pass.

Existing operational runbooks remain the source for the external certification step:

- `docs/operations/BACKUP_AND_RESTORE_RUNBOOK.md`
- `docs/operations/DEPLOYMENT_RUNBOOK.md`
- `CHIPPER_FARMS_EXTERNAL_CERTIFICATION_RUNBOOK.md`

External certification must prove:

- encrypted backup from a production-like MariaDB database;
- off-host storage;
- restore into an isolated restore database;
- migration compatibility after restore;
- documented RPO/RTO and rollback owner.
