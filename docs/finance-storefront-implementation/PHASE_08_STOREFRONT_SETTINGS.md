# Phase 8 — Storefront settings and catalog configuration

## Document control

| Field | Value |
| --- | --- |
| Phase | 8 — Storefront settings and catalog configuration |
| Date | 2026-08-09 |
| Branch | `staging` |
| HEAD at phase start | `929b4e4` |
| Blueprint anchor | §34 (storefront settings), §59 acceptance "storefront settings control public category/payment/delivery behaviour" |
| Depends on | Phase 1 (feature flags), Phase 2 (tax profiles) — both COMPLETE |
| Phase status | **COMPLETE** |

## 1. What this phase found before it built anything

Phase 1 added thirteen `storefront.*` feature flags. A grep of the route table
and the application for their enforcement returned this:

```
$ grep -rn "'feature:" routes/web.php
routes/web.php:468   finance.overview
routes/web.php:480   finance.quick_expense
routes/web.php:515   finance.simple_purchase
```

**Not one storefront flag was enforced anywhere.** A farm could switch Manure
Sales off and carry on selling manure. It could switch Paystack off and carry on
taking card payments. It could switch the entire store off — `storefront.enabled`
and the global `storefront.enabled` setting both — and stay open for business,
because `StorefrontSettings::enabled()` had no callers at all.

That is worse than having no flags. An administrator reads a settings screen and
believes it. A configuration the application does not honour is a promise to a
customer that the software silently breaks.

So Phase 8 is not "add a settings screen". It is: make the switches real, then
give the farm somewhere to work them.

## 2. Architecture decisions

### 2.1 Organisation-scoped storage, not `system_configs`

`StorefrontSettings` read the `storefront.*` namespace out of `system_configs`,
which Phase 0 §6 established has no `organization_id` (IMP-001). Every farm on
the installation shared one store name, one support email, one pickup address.

New table `storefront_settings`, one row per organisation, `organization_id NOT
NULL` with a unique index. This is the same answer Phase 1 gave for feature
flags, for the same reason, and it closes the storefront half of IMP-001.

The other half — `sales.pricing.*`, which prices real sales — is deliberately
untouched. Those keys drive the operational sale path as well as listing
defaults, and a listing's "default from system settings" price must match what a
recorded sale would charge. Splitting them belongs with the operational pricing
work, not here. IMP-001 stays open, narrowed.

### 2.2 Typed columns, not a second key/value store

A second key/value table would inherit the first one's real defect: nothing can
be validated at the storage boundary. `checkout_hold_minutes` could hold "soon";
`default_tax_profile_id` could point at another farm's profile. Columns let the
database have a say — a foreign key on the tax profile, a bounded unsigned
integer on the hold, a unique organisation.

### 2.3 One switch per behaviour

Blueprint §34 lists "payment methods enabled", "public categories enabled",
"delivery enabled" and "guest checkout enabled" as *settings*. Blueprint §32
already defines them as *feature flags*. Implementing both would give one
behaviour two switches, and an administrator who turns COD on in one place and
finds it still off in the other.

Resolution: **the settings screen writes the flags.** `StorefrontCatalog` and
`StorefrontSettings` read them, so no caller has to know which store answers a
given question. `storefront_settings` holds only the values a flag has no room
for — store name, contacts, pickup address, hero copy, hold minutes, tax display
mode, default tax profile, refund policy — plus delivery zones in their own
table.

A test asserts the duplicate columns do not exist:

```php
foreach (['cod_enabled', 'paystack_enabled', 'delivery_enabled', 'manure_sales_enabled'] as $duplicate) {
    $this->assertFalse(Schema::hasColumn('storefront_settings', $duplicate), ...);
}
```

### 2.4 The one apparent exception, and why it is not one

"Store enabled" *is* a settings column (`public_shop_open`), not the
`storefront.enabled` flag. This looks like the duplication §2.3 just rejected. It
is not, and the reason is mechanical.

`OrganizationFeatureService::disable()` cascades to dependants — correctly, and
by design (Phase 1 §2.5). Every storefront flag declares
`requires: [storefront.enabled]`. So an administrator who unticked "store
enabled" on this screen would switch off Paystack, COD, delivery, quotes,
customer accounts and all six category flags. Re-ticking it would not bring them
back. A farm pausing its shop for a season would lose its entire configuration.

They are also genuinely different questions:

| Question | Owner | Effect |
| --- | --- | --- |
| Do you have a storefront at all? | Modules screen, `storefront.enabled` | Removes the back-office workspace too |
| Is the shop open right now? | This screen, `public_shop_open` | Public pages close; everything else untouched |

`StorefrontSettings::enabled()` requires both. There is a test that closing the
shop leaves every other switch alone.

### 2.5 A closed shop answers 503 with a page, not 404

`EnsureFeatureEnabled` returns 404, which is right for a finance module that is
not part of an organisation's product. A closed shop is different: it is a real
shop that is temporarily not selling, and its visitors are members of the public
with no other way in. A bare 404 is exactly the dead end blueprint §46 exists to
prevent.

They get a page naming the farm and its phone and email, served 503 so crawlers
treat the closure as temporary rather than de-indexing a farm's shop because it
paused for a season.

**Deliberately not closed:** the Paystack webhook and the payment callback. Money
already in flight must still be verified; a closed shop must never strand a
payment a buyer has already made. Existing customers' order pages stay open for
the same reason. There is a test.

### 2.6 Delivery zones price by destination, and never alongside per-listing fees

`storefront_listings.delivery_fee` charges by *what* is bought. A farm delivering
the same pig to the next village or to Kumasi pays very different money for the
same animal, so the real driver is *where* it goes — blueprint §34 "delivery
zones/fees: new".

Both charging at once would bill one journey several times, once per animal in
the order. The rule, implemented in `CheckoutService` and stated on the settings
screen:

- Organisation **has active zones** → the buyer picks one, its fee is the
  delivery fee, per-listing fees are ignored.
- Organisation **has no zones** → per-listing fees apply exactly as before.

Both paths are tested, including the one that proves 500.00 + an 85.50 zone
becomes 585.50 and not 605.50.

The zone id is re-read from the database inside checkout and scoped to the
organisation and to active zones. A posted `delivery_zone_id` is buyer input like
any other; another farm's zone must not price this farm's delivery. Tested.

The order records `delivery_zone_id` **and** `delivery_zone_name`. The name is
snapshotted because a farm that raises the Kumasi fee in March must still be able
to explain what a February order was charged.

### 2.7 The settings screen is not behind the flag it controls

Gated on `manage_storefront_settings` (which already existed, granted to admin
and superuser) and deliberately **not** on `feature:storefront.enabled`. This is
the screen that explains why a shop is dark. Locking the recovery surface behind
the thing that is switched off turns a configuration mistake into a support call.
Tested with the module disabled.

### 2.8 Paystack health, never the secret

Blueprint §34: "config health display only, never expose secret". The screen
reports configured/not, mode (live/test), and whether a public key is present. It
does not render the key, not even partially masked — four characters of a live
secret in a support screenshot is still four characters of a live secret. Tested
with `assertDontSee` against both the whole key and its tail.

## 3. What was built

| Area | Delivered |
| --- | --- |
| Schema | `storefront_settings`, `storefront_delivery_zones`, `storefront_orders.delivery_zone_id` + `delivery_zone_name` |
| Backfill | Global `storefront.*` values carried onto every organisation; idempotent, resumable, chunked, skips organisations that already have a row |
| Resolution | `StorefrontSettingsResolver` (container singleton, per-organisation cache, invalidated by model events) |
| Accessors | `StorefrontSettings` rewritten organisation-aware, same static API so the twelve Blade call sites keep working |
| Gating | `StorefrontCatalog` — kind → flag and payment method → flag, in one place |
| Public shop | Home, browse, search, category cards, placements and listing detail all constrained to enabled kinds; nav and hero buttons follow |
| Closed shop | `EnsureStorefrontOpen` middleware + closed page |
| Cart | Adding a listing of a switched-off kind is refused |
| Checkout | Payment method and fulfilment validated against what the farm offers, in the form request **and** again in the service; zone pricing; refund policy shown before payment |
| Quotes | `storefront.quotes` enforced on both the form and the endpoint |
| Admin | `storefront-admin/settings` — status, store details, categories, payments + Paystack health, checkout, tax, delivery zone CRUD |

### 3.1 Enforcement is doubled on the money path

`CheckoutRequest` validates the payment and fulfilment methods against the
organisation's enabled lists. `CheckoutService::assertOfferedTerms()` checks the
same things again. That is not redundancy for its own sake: a form request guards
the HTTP path only, and the service is reachable from tests, console tooling and
any later caller. An order taken by a method the farm cannot settle is not a
validation slip — it is money it has no process to collect.

### 3.2 Where the shop is empty, the page says so

A farm with neither Paystack nor COD enabled cannot take an order. The checkout
page says so, with the farm's phone and email, and withholds the submit button
rather than letting the buyer fill in a form that validation will reject
(blueprint §46).

## 4. Tests

41 new tests across three files.

| File | Tests | What it guards |
| --- | ---: | --- |
| `StorefrontSettingsEnforcementTest` | 18 | Do the settings actually control anything |
| `StorefrontSettingsScreenTest` | 17 | Permissions, organisation scoping, validation, secrets |
| `StorefrontSettingsMigrationTest` | 6 | Schema shape, unique constraints, backfill idempotency |

The enforcement file is the one that matters. Each test is written as a way this
regresses: a switched-off category still on the shop, still reachable by direct
URL, still addable to a cart; a switched-off payment method still accepted by the
service and by HTTP; delivery accepted by a farm that does not deliver; a closed
shop still selling; another farm's zone pricing this farm's delivery.

### 4.1 Verified in both directions

Definition of done item 5. Two defects were reintroduced deliberately and the
suite re-run.

| Defect reintroduced | Result |
| --- | --- |
| `StorefrontCatalog::isKindEnabled()` and `isPaymentMethodEnabled()` → `return true` | **7 of 17 enforcement tests fail** |
| `StorefrontSettings::enabled()` ignores `public_shop_open` | **2 tests fail** — `test_closing_the_shop_closes_the_public_pages`, `test_a_closed_shop_refuses_a_new_order` |

Both files were restored from backup and the suite re-run green before
continuing.

### 4.2 A test artefact that produced a real improvement

`test_closing_the_shop_closes_the_public_pages` first failed with 200 instead of
503. The cause was the resolver's per-organisation cache: the test created the
settings row with Eloquent directly, bypassing `save()`, so the cache held the
earlier read. In production this is invisible — one request, one container — but
that is exactly what makes it worth fixing rather than working around.

`StorefrontSetting` and `StorefrontDeliveryZone` now invalidate the resolver on
`saved` and `deleted`, so any writer — seeder, console command, test — is
covered, not just the settings controller. The manual `forget()` calls that were
papering over it in two tests were removed, so those tests now also prove the
invalidation works.

## 5. Commands run and exact results

```
$ ./vendor/bin/phpunit --filter Storefront
OK (165 tests, 484 assertions)

$ ./vendor/bin/phpunit
Tests: 1575, Assertions: 4663, Skipped: 13        # 0 failures, 0 errors

$ bash scripts/testing/run-mariadb-tests.sh        # MariaDB 11.4.2, port 3399
Tests: 1575, Assertions: 4673, Skipped: 7          # 0 failures, 0 errors
```

Migration reversibility, verified on both engines:

```
$ DB_CONNECTION=sqlite ... php artisan migrate:rollback --step=1 --force
  2028_09_08_100000_create_storefront_settings_tables ... DONE
$ ... php artisan migrate --force
  2028_09_08_100000_create_storefront_settings_tables ... DONE

# MariaDB 11.4.2, scratch database cf_rollback_probe: same, then dropped.
```

Schema as created on MariaDB 11.4.2:

```
UNIQUE KEY `storefront_settings_org_unique` (`organization_id`)
CONSTRAINT `storefront_settings_default_tax_profile_id_foreign`
  FOREIGN KEY (`default_tax_profile_id`) REFERENCES `tax_profiles` (`id`) ON DELETE SET NULL
CONSTRAINT `storefront_settings_organization_id_foreign`
  FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
UNIQUE KEY `storefront_delivery_zones_org_name_unique` (`organization_id`,`name`)
```

## 6. Failures found and corrected during the phase

### 6.1 An existing test that the new enforcement correctly broke

`FinanceIntegrationTest::test_non_postable_line_is_deferred_not_posted` sells
manure. `storefront.manure_sales` defaults to **off** for a new organisation, and
a test organisation is new, so the cart correctly refused it.

The test was fixed by enabling the feature, not by weakening the guard — a farm
that sells manure switches it on, and so does its test. `StorefrontTestCase` now
carries `enableStorefrontFeature()` / `disableStorefrontFeature()` helpers.

### 6.2 The last two MariaDB gate failures, closed

The MariaDB record stood at **5** residual failures after the previous session
(`MARIADB_11_4_GATE_FINDING.md` closing state). Three were fixed in commits
`0ea6634` and `2939998`. The remaining two —
`DerivedOrganizationScopeTest::test_mating_derives_organization_from_female` and
`::test_mating_is_not_visible_across_organizations` — failed here with a
different cause from the one recorded:

```
SQLSTATE[01000]: Warning: 1265 Data truncated for column 'status' at row 1
insert into `matings` (... `status` ...) values (..., pending, ...)
```

The fixture set `status => 'pending'`. `matings.status` is
`enum('planned','completed','cancelled','failed')`; `pending` is a valid
**`pregnancy_status`**, a different column. The fixture had confused the two.
SQLite has no enum, so it passed there and only MariaDB caught it.

Not a production defect — no application path writes `pending` to that column —
and both tests assert organisation derivation and cross-tenant visibility, so any
valid status serves. Fixed with a comment recording why.

**The MariaDB 11.4.2 gate is now at zero.** 42 → 0.

## 7. Deliberately not done in this phase

Recorded so the next session does not have to rediscover the reasoning.

| Item | Why not here |
| --- | --- |
| `storefront.guest_checkout` enforcement | There is no guest checkout yet — checkout requires authentication. The accessor exists; Phase 9 builds the flow it gates. |
| `storefront.customer_accounts` enforcement | Blocking the account area would hide existing customers' orders. Belongs with Phase 14 advanced gating, alongside a decision about what an existing account holder sees. |
| Tax display mode applied to displayed prices | Stored and exposed; the shop currently shows one price. Applying inclusive/exclusive presentation needs the storefront tax calculation Phase 12 introduces, and a display that disagreed with what an order posts would be worse than none. |
| Default tax profile applied to orders | Same: it is configuration Phase 12's storefront→finance integration consumes. Selecting it now means the farm is not asked for it mid-integration. |
| `checkout_hold_minutes` consumed | Phase 10 owns holds. The setting exists so the hold work does not also have to build its own configuration surface. |
| The rest of IMP-001 (`sales.pricing.*`) | Drives the operational sale path, not just storefront listings. Splitting it is operational pricing work. |

## 8. A behaviour change worth stating plainly

For organisations that existed before Phase 1, the backfill enabled the entire
catalogue, so nothing they could reach yesterday becomes unreachable today.

For a **new** organisation, three flags default to off — Manure Sales, Services
and Delivery — and as of this phase those defaults are enforced. A new farm that
sells manure or delivers must say so on the settings screen. That is the intended
behaviour of a default, and it is now the first thing the screen shows.

## 9. Status

**Phase 8: COMPLETE.**

Blueprint §59's acceptance criterion for this phase — "storefront settings
control public category/payment/delivery behaviour" — is met and tested in all
three directions, on SQLite and on MariaDB 11.4.2.
