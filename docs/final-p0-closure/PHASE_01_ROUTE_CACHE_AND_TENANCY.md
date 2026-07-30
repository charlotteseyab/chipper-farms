# Phase 01 Route Cache And Tenancy

Status: Complete

## Finding

Organization-scoped route model bindings were registered in Laravel's routing bootstrap `then` callback. Cached routes do not replay that callback, so cached production boots could resolve cross-tenant records before policy checks, producing 403 instead of the intended 404.

## Correction

The `pig`, `farrowing`, `mating`, and `weaning` route binders now register from `App\Providers\AppServiceProvider::boot()`. The binding logic still resolves tenancy through `organization_id` or the `ResolvesOrganization` contract and aborts with 404 when no current organization exists or the record belongs to another organization.

## Evidence

- Targeted cross-tenant pig test passed with route cache cleared.
- Targeted cross-tenant pig test passed with route cache enabled.
- Security feature suite passed with route cache enabled: 71 passed, 84 assertions.
- Security feature suite passed with route cache cleared: 71 passed, 84 assertions.

## Gate

Status: PASS

