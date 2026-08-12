# Phase 11 - Operational Gates

Operational items addressed in repository tooling:

- Cache tests now build and verify cached Laravel config before running.
- Cache cleanup is trapped on exit.
- MariaDB helpers refuse nonlocal hosts and unapproved database names.
- XAMPP `3306` certification can bypass the gitignored `3399` override intentionally with `CF_SKIP_MARIADB_LOCAL_ENV=yes`.

External runtime operations such as supervisor queue operation, scheduler operation, Paystack sandbox operations, and production storage/signed URL validation remain external certification items.
