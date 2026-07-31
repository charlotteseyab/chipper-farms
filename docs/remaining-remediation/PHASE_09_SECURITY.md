# Phase 09 - Security

Security and dependency checks executed:

- `composer validate --no-check-publish`: passed.
- `composer audit`: passed on retry with no advisories.
- `npm audit --audit-level=low`: passed with 0 vulnerabilities.
- full default Laravel suite: 1309 passed / 13 skipped / 3739 assertions.
- route authorization suites passed as part of the full suite.

Note: the first `composer audit` retry during this pass timed out against Packagist, then passed on retry.
