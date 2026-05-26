# Changelog

## 2026-04-14 Security and stability update

### Added

- Upgrade guide for production-safe deployment
- Release notes for administrators
- Root `.gitignore` with production-safe exclusions
- Migration to add `date_weaned` to the `farrowing` table

### Changed

- Restricted privileged dashboard and settings routes with role middleware
- Restricted feed approval and sensitive feed stock actions to managerial roles
- Restricted user role values to supported system roles only
- Stopped auto-creating arbitrary roles from user management flows
- Switched purchase request numbering away from `max(id) + 1`
- Throttled `last_seen` activity writes to reduce unnecessary database load
- Sanitized email test error messages returned to the UI
- Aligned `RawMaterial` model fields with the current schema (`unit_cost`)
- Hardened the `local_id` migration so it no longer assumes `workspace_id` exists
- Neutralized the duplicate `purchase_requests` migration when the table already exists
- Guarded the destructive feed cleanup migration behind an explicit environment flag
- Fixed system settings rollback to target the correct table

### Fixed

- Fixed broken `health.update` route binding to use `PigHealthController`
- Added missing health record update handler
- Completed medication update handler logic
- Added missing purchase request destroy action
- Prevented approval, decline, and mark-paid purchase request actions for unauthorized roles
- Prevented raw material deductions from reducing stock below zero silently
- Removed duplicate feed routes
- Removed notification side effects from weaning alert read logic
- Added `date_weaned` support expected by farrowing views and controllers

### Operational notes

- Default seeded users are now disabled outside local/testing unless explicitly enabled
- Shared default passwords were removed from the main seed path
- Packaging guidance now excludes logs, compiled views, local sqlite, and `public/hot`
