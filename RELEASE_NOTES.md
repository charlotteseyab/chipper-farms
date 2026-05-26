# Admin Release Notes

## What changed

This update focuses on production safety, access control, and stability inside the existing Chipper Farms codebase.

## Key improvements

- stronger protection on privileged pages and actions
- safer user role handling
- safer purchase request approvals and numbering
- safer raw material stock deductions
- fix for farrowing/weaning records expecting `date_weaned`
- cleanup of duplicate and broken route definitions
- improved deployment documentation and packaging discipline

## What admins should verify after deployment

- user roles still match expected access
- superuser-only pages remain superuser-only
- admin and manager operational pages still work as expected
- purchase requests can still be created, approved, declined, and paid correctly
- feed stock figures remain correct after adjustments
- farrowing and weaning records display correctly

## Behavior changes to note

- unsupported free-text roles are no longer accepted
- default seed users are not created in production unless explicitly enabled
- some feed management actions are now restricted to admin, manager, and superuser roles
- stock deductions that exceed available stock are now blocked instead of being silently forced to zero

## Recommended communication to farm staff

Tell staff:

- the system is being hardened for production safety
- daily workflows should remain the same
- access to management-only pages may now be stricter
- any access issue should be reported immediately after release testing
