# Roles and permissions follow-up — 28 October 2026

Result: the four previously reported integration defects are corrected in the
current local code and have regression coverage. One additional dashboard
permission mismatch remains. This review changed no application code or grants.

## Remaining finding — Medium

`app/Services/Access/DashboardAccess.php:85` treats all controller-gated routes
under a prefix as read operations. For example, `all-stocks.create` and
`all-stocks.import.create` use the same `view_pigs` check as `all-stocks.index`.
The actual create controller requires `manage_pigs`.

The data-entry dashboard uses this helper to filter quick-entry links
(`app/Http/Controllers/DataEntryController.php:99`). Two HTTP reproductions
through farm role customization confirm both directions of the mismatch:

- Grant only `view_pigs`: the dashboard displays Add a pig and Import stock,
  but the create page returns 403. `DashboardAccess::canOpen()` returns true.
- Grant only `manage_pigs`: the create page opens successfully, but the
  dashboard hides Add a pig. `DashboardAccess::canOpen()` returns false.

The second combination is supported by the Custom permission controls. The
backend continues to enforce its permission checks, so this finding is a
navigation/authorization-consistency defect rather than an unauthorized write.

Recommendation: map controller-gated routes to their actual policy abilities or
permissions, distinguishing read/create/import actions. Do not infer a write
route's authorization from its area's view permission. Add tests for view-only
and record-only custom grants; inspect the other mapped route prefixes for the
same mismatch.

## Previous findings rechecked

- Data-entry recent entries, manager panels, and generic dashboard sections now
  consult effective permissions; integration tests cover revoked/partial access.
- The daily-weight API now checks `view_weights`, validates and bounds its date
  range, and uses one grouped query rather than one query per day.
- Structure reads and the sidebar use `view_facilities`; mutation controls and
  routes retain management checks.
- Login and sidebar use the shared LandingPage resolver; customized accountant
  access has a tested permitted fallback.

## Validation

- Authorization, security, accountant middleware, storefront permissions and
  dashboard suites: **177 tests, 523 assertions, no failures**.
- Two temporary HTTP/helper reproduction probes confirmed the remaining defect:
  **2 tests, 12 assertions**. These assert observed buggy behavior and do not
  imply a fix.
- Temporary probe: `/tmp/RoleAccessRecheckProbeTest.php`.
- Output: `/tmp/chipper-role-recheck-tests.log` and
  `/tmp/chipper-role-recheck-probes.log`.

Checks used isolated SQLite. Live farm grants, production deployment state,
MariaDB concurrent permission updates, and browser interaction with the role
settings form were not verified. The original review document remains a
historical record; this follow-up supersedes its open-finding status.
