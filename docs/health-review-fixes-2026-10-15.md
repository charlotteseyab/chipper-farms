# Health and medication review fixes — 15 October 2026

## Phase 1: follow-ups and group history

Completed courses retain pending follow-ups in the worklist, counts, and due/overdue reminders. Stopped courses and departed animals are excluded. A linked case's follow-up recorded on or after the due date, or its resolution/closure, clears that work.

Group case members appear in the health overview and its filters. Each member's latest health summary and PDF include group cases as well as individual records. Legacy individual records remain supported.

## Phase 2: dosing workflows

Recording part of a scheduled group only closes schedules for selected animals. The slot IDs retained by the form for removed animals leave their schedules untouched. Medicine, course, and duplicate schedule checks remain enforced.

Prescribing and amending reject animals outside the linked case. The existing two-medicine scheduling test now creates a group case for its two animals.

Correcting to another medicine releases the original scheduled dose and records the replacement without marking that original prescription administered. Stock reversals and replacement recording remain one transaction.

## Phase 3: receipt dates

Automatic selection excludes batches received after the administration date. Explicit batch selection rejects them with a validation message. A batch received on the same date remains eligible, subject to expiry and stock checks. This guards receipt chronology; it does not reconstruct historical inventory balances.

## Validation

Regression coverage is in `tests/Feature/Health/HealthReviewRegressionTest.php`, including overview filters, PDF input, reminder/worklist follow-ups, partial dosing, correction, case membership, and automatic/explicit receipt-date checks.

Run: `php artisan test tests/Feature/Health tests/Feature/Operations/HealthEndpointTest.php`.

Result: 124 tests passed with 827 assertions, including 10 new regression tests.

Browser interaction and MariaDB concurrency were not exercised in this change.
