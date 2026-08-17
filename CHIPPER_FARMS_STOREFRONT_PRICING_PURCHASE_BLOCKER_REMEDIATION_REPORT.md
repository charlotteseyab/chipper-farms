# Chipper Farms Storefront Pricing + Purchase Flow Blocker Remediation Report

Status: Remediated and certified for human smoke test.

## Scope

This remediation addressed the failed storefront pricing and purchase flow audit covering:

- Storefront push category authority.
- Breeding, standard, piglet, and manual source behavior.
- Live and carcass purchase snapshots through checkout, allocation approval, and `/sales`.
- Manual storefront lines through finance without fake `PigSale` records.
- Empty stock-backed listing prevention.
- Search and selector usability for stock-backed listings.

## Remediated Items

- `CF-SPP-P0-001`: Breeding-stock listings now accept breeding stock pools and compute availability from the correct required stock type.
- `CF-SPP-P0-002`: Live and carcass allocations now persist allocation commercial snapshots and call `RecordPigSaleService` with correct weight type, sale weight, price per kg, and sale amount.
- `CF-SPP-P0-003`: Manual storefront lines now route through `InvoiceService` and `InvoicePostingService`; they no longer skip finance and do not create fake `PigSale` records.
- `CF-SPP-P1-001`: Stock-backed hidden listing fields are overwritten server-side from source category.
- `CF-SPP-P1-002`: Standard and breeding listings with no selected pigs fail validation before listing creation, and create-plus-attach is atomic.
- `CF-SPP-P2-001`: Stock search now includes local ID, code, and latest/current weight; the client filter carries matching row metadata.

## Certification

The full storefront feature suite passed:

`php artisan test tests/Feature/Storefront`

Result: 400 passed, 1224 assertions.

