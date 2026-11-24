# Roles and permissions review — 28 October 2026

Result: changes are not ready for access-control sign-off. Four reproducible
integration defects remain. Application code was not changed during this review.

## Scope and intended behavior

Reviewed the new farm role settings migration, permission resolver, save/reset
service, Settings → Roles & access controller and view, middleware, policies,
seeders, navigation, dashboards and login redirects.

The application supports superuser, admin, farmmanager, dataentry, accountant,
treasurer, salesperson, customer and mill_operator. The new screen customizes
six existing staff roles: farmmanager, dataentry, accountant, treasurer,
salesperson and mill_operator. It does not create arbitrary new roles. Admin,
superuser and customer cannot be edited through this screen.

Uncustomized roles combine stored default grants with the livestock defaults
in code. Customized roles use the farm-specific grant snapshot. Hidden module
permissions are retained on save, so switching a feature off is not itself a
permission revocation. Feature gates must still enforce module availability.

## Findings

### 1. High — Revoking view permissions does not remove dashboard disclosure

`app/Http/Controllers/DataEntryController.php:124` builds recent-entry data for
weights, feeding, medication, pigs and mating without checking the corresponding
view permissions. Dashboard admission is still role-based
(`routes/web.php:207`). Filtering task links and stat cards does not filter the
recent-entry list or the missing-record details.

Reproduction: an admin saves an empty permission set for dataentry. A fresh
user object correctly fails `view_pigs`, and `/all-stocks` returns 403. The same
user gets 200 from `/dataentry/dashboard`, which renders the code of a newly
created pig. The permission change therefore does not provide the promised
confidentiality boundary.

The manager dashboard also calculates broad operational metrics without
permission checks (`FarmManagerDashboardController.php:21`). The generic
`/dashboard` includes livestock overview components without an outer permission
gate. These related surfaces need the same review when fixing disclosure.

Recommendation: filter both queries and output sections by effective view
permissions. Add negative HTTP tests for every affected dashboard after grants
are revoked. Keep a neutral landing page available when nothing is granted.

### 2. High — Daily-weight API ignores revoked weight permission

`routes/web.php:209` protects `/api/daily-weights-data` only by staff role.
`DataEntryController.php:170` does not check `view_weights`.

Reproduction: after the same empty dataentry permission update, `/weights`
returns 403 but `/api/daily-weights-data` returns 200 with labels and counts.

Recommendation: enforce `view_weights` at this endpoint and test revocation.
The endpoint also accepts unrestricted date ranges and performs one query per
calendar day; bound and validate the range as part of the correction.

### 3. Medium — View-only facilities access cannot open facilities

`StructureController.php:13` and `:44` require `manage_facilities` even for index
and show. Their routes correctly require `view_facilities`. Navigation also
uses `manage_facilities` for the Structures & Rooms link
(`NavigationHelper.php:126`).

Reproduction: granting only `view_facilities` through the new settings screen
produces a user with that grant and no management grant. `/structures` still
returns 403, and its sidebar link is absent.

Recommendation: use the view permission for read actions and navigation, retain
the management permission for writes, and permission-gate mutation controls in
the views. Check room read actions at the same time.

### 4. Medium — Custom roles can land on forbidden pages after login

`FortifyServiceProvider.php:162` always sends accountants/treasurers to
`finance.overview`; `NavigationHelper.php:389` chooses the same dashboard by
role rather than effective grants. Salesperson login similarly always selects
the storefront dashboard.

Reproduction: customize accountant to hold `view_pigs` only. LoginResponse still
redirects to `finance.overview`, and requesting that destination returns 403.
The role can legitimately have livestock access while lacking finance access.

Recommendation: resolve the landing page from effective permissions and feature
availability, with a safe fallback; apply the same rule to login and sidebar
Dashboard links. Add accountant, treasurer and salesperson customization tests.

## Checks that passed

The existing test suite checked farm-specific changes, reset behavior, default
access, non-admin denial, uneditable privileged/customer roles, platform grant
filtering, tenant scope, route authorization and navigation. The role settings
controller resolves a farm admin's own organization rather than trusting a
submitted farm id. Superusers select the target farm explicitly. Save/reset
synchronize matching legacy role aliases. Server-side permission filtering
rejects arbitrary/platform-only submitted grants.

These are useful protections; they do not cover the four defects above.

## Evidence and limitations

- Existing authorization/security, accountant middleware and storefront
  permission tests: **167 tests, 462 assertions, no failures**.
- Four temporary HTTP/login/navigation reproduction probes: **4 tests,
  20 assertions**; all confirmed the current defective behavior. These passing
  reproduction checks are evidence of bugs, not evidence that they are fixed.
- Temporary probe source: `/tmp/RoleAccessReviewProbeTest.php`.
- Temporary output: `/tmp/chipper-role-review-tests.log` and
  `/tmp/chipper-role-review-probes.log`.
- Tests used isolated SQLite with RefreshDatabase. Production role assignments,
  deployment migration state, MariaDB concurrent role changes and browser
  interaction with the Alpine permission controls were not verified.

No production grants were inspected or modified. Existing workspace edits were
preserved. This report is the only repository file added by this review.
