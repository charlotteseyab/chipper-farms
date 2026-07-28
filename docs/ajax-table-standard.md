# AJAX Table Standard

Large operational and reporting tables should use server-side filtering with AJAX updates. Keep full page loads working and return a Blade partial for AJAX requests unless a page already has a JSON frontend.

## Pattern

- Build one controller query from request filters.
- Paginate the query.
- Return the full Blade page for normal requests.
- Return a rendered table partial for AJAX requests.
- Use `resources/js/ajax-table.js` for debounced filters, request cancellation, pagination interception, loading state, errors, and URL updates.
- Keep authorization and organization scoping in the controller/policy layer.

## Page Inventory

Operational tables to prioritize for AJAX:

- Pig stock: `stocks/all/index.blade.php`, `stocks/standard/index.blade.php`, `breeding_stocks/index.blade.php`, `pigs/archived.blade.php`
- Purchased stock: `purchase-stock-intakes/index.blade.php` converted
- Breeding operations: `farrowing/index.blade.php`, `mating/manage.blade.php`, `gestation/index.blade.php`, `weaning/dashboard.blade.php`
- Weight operations: `weights/index.blade.php`, `weight-alerts/index.blade.php`, `weight-standards/index.blade.php`
- Health operations: `health/index.blade.php`, `health/medications-index.blade.php`
- Mortality and sales: `deaths/index.blade.php` converted, `sales/index.blade.php` converted
- Feed and procurement: `feeds/raw-materials/index.blade.php`, `feeds/purchase-requests/index.blade.php`, `feeds/feed-production/index.blade.php`, `feed/issues/index.blade.php`
- Storefront admin/customer lists: storefront listing, order, quote, and shop index pages
- Finance lists: invoices, receipts, payments, journals, suppliers, customers, bank statements, loans, close periods, WHT certificates, supplier statements

Reporting tables to prioritize when result sets are large or expensive:

- `settings/reports.blade.php`
- finance reports and ageing pages
- inventory value/opening balance pages
- costing group pool and writeoff pages

Small local tables that can stay client-side:

- Rooms inside one structure
- Pigs within one room
- Tables on detail/show pages such as one pig's weights, health records, or sale history
- Modal item tables on create/edit screens
- PDF/export-only tables

## New Table Checklist

1. Add a GET filter form with `data-ajax-table-form`.
2. Wrap the rendered table partial in `data-ajax-table-target`.
3. Return `{ html, url }` from the controller when `$request->ajax()`.
4. Keep the same route working without JavaScript.
5. Add a focused feature test for normal load, AJAX response, filters, pagination, authorization, and organization scoping where applicable.
