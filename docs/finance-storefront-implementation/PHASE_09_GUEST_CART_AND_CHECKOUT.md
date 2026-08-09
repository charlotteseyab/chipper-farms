# Phase 9 — Guest cart and checkout

## Document control

| Field | Value |
| --- | --- |
| Phase | 9 — Guest cart and checkout |
| Date | 2026-08-09 |
| Branch | `staging` |
| HEAD at phase start | `4d3e474` (Phase 7) |
| Blueprint anchor | §38 (checkout experience), §17 (essential storefront scope) |
| Depends on | Phase 8 — COMPLETE |
| Phase status | **COMPLETE** |

## 1. The gap

§17 lists "guest cart/checkout — buyer can order without creating account
first" as **essential** storefront scope, and §38 requires the cart to work
"before login through session/cart token".

Neither existed. `storefront_carts.user_id` was `NOT NULL`, `CartService` took
`User $user` in every method, and every cart and checkout route sat behind
`auth:sanctum`. A buyer had to register before they could put anything in a
basket. For a farm shop that is the difference between an order and a bounce.

## 2. Architecture decisions

### 2.1 The cart is a row addressed by a token, not session state

Keeping the cart in the session would lose it whenever the session rotated —
which Laravel does on login, exactly when a buyer is most likely to have a full
basket. A database row addressed by an opaque cookie token survives the login,
survives a browser restart, and lets one merge path handle both "signed in
halfway through" and "came back tomorrow".

The token is 64 hex characters from the CSPRNG, httpOnly, 60 days. It carries no
information about the buyer, so a leaked token discloses a basket and nothing
else. A cookie that does not match `^[a-f0-9]{64}$` is treated as absent rather
than used as a lookup key.

### 2.2 A guest is a different kind of owner, not a missing user

`CartService` could have taken `?User`. That would have meant every method
growing a null branch, and — the part that matters — `assertOwned()` silently
passing for *every* guest cart the first time someone forgot one.

`CartOwner` makes the two cases explicit and gives `owns()` one place to answer
"is this cart yours". It is written as a pair of positive checks rather than
"not someone else's", so a cart with neither a user nor a token belongs to
nobody rather than to everybody. `CartOwner|User` unions on the service methods
mean every existing authenticated call site kept working unchanged.

### 2.3 Tokens are minted on write, never on read

`current()` returns null for a visitor who has never had a cart;
`currentOrCreate()` is called only when an item is actually added. Otherwise
every anonymous page view would set a cookie and leave an empty cart row behind
it. There is a test that browsing creates no cart.

### 2.4 A claim token, not an email match — the decision this phase turned on

§38 asks for "optional account creation after order". The obvious implementation
is to attach guest orders to whoever later registers with the same email. That
makes a stranger's order history a prize for registering with their address, and
this application does not prove email ownership before login.

Each guest order therefore carries a single-use `guest_claim_token`, delivered
in the confirmation URL the buyer already holds. Claiming requires presenting
it, which proves the claimant is the person who stood at the checkout. The token
is cleared the moment it is used, so a confirmation link forwarded to a
colleague cannot be replayed to attach the order to a second account.

The same token is what lets a guest track the order without an account at all —
§38's recovery row, which for a guest has nowhere else to lead.

Three properties are enforced in `GuestOrderController::locate()`:

- The lookup requires reference **and** token. A lookup by reference alone would
  make every order readable by anyone who can count.
- The token is compared with `hash_equals`, not in the `WHERE` clause. A
  database comparison on a secret is a timing oracle and, on some collations, a
  case-insensitive match.
- Candidates are fetched with `get()`, not `first()`. The reference is unique per
  *organisation*, not globally, so two farms can both hold `SO-0001`; taking the
  first row and testing its token would 404 a valid link whenever another tenant
  sorted ahead.

### 2.5 Guest counterparty matching is by email only

A guest order still reaches finance, and finance is built on counterparties — so
"no account" means no `User`, never no counterparty.

Matching on phone was considered and rejected: phone numbers are shared within
households and re-issued by networks, so a match would silently post one
person's order onto another person's ledger. Email matching is the rule
`ensureCounterparty()` already applies to registered buyers, so a guest who later
registers with the same address lands on the counterparty they were already
using. A guest with no email gets a fresh counterparty every time — a duplicate
contact record is a tidy-up job; a wrong match is someone else's purchase
history and credit balance.

### 2.6 Merging adds and caps rather than overwriting or dropping

When a guest signs in, their cart merges into their own. Quantities add; a
merged line that no longer fits available stock is **capped at what is
available** rather than dropped, so the buyer keeps what they can still have and
finds out now rather than at checkout after entering their details. The guest
cart is retired to `abandoned`, not deleted.

### 2.7 Routes moved rather than duplicated

Cart and checkout moved out of `/account` and into the public `/shop` prefix,
renamed `storefront.cart.*` and `storefront.checkout.*`. Keeping a second public
set alongside the old authenticated one would have meant two routes for one
screen and two places to remember a gating rule. All 16 references were updated.

The tracking and claim routes sit **outside** the `storefront.open` gate, for
the same reason as the Paystack webhook: an order already placed must stay
visible to whoever placed it, whatever the farm has since done with its shop.

### 2.8 Checkout is now a throttled unauthenticated POST

It was authenticated before, so it carried no rate limit. Ten a minute, keyed by
user id where there is one so several buyers behind one office NAT do not
throttle each other. Each attempt mints an order reference from the numbering
sequence and can create a counterparty; that is not something to leave open to a
script.

### 2.9 A farm that requires accounts says so

`storefront.guest_checkout` is enforced in `CheckoutService`, not just in the
view. A guest reaching checkout at such a farm sees the page with a sign-in
prompt and the note that their cart is saved — not a 404 on a full basket
(§46). The endpoint refuses independently, because hiding a form is not
enforcing it.

## 3. Tests

19 new tests in `GuestCheckoutTest`, plus one existing test rewritten.

| Group | What it guards |
| --- | --- |
| The journey | Basket survives the request, guest order carries identity and a counterparty, buyer lands somewhere they can return to, cookie cleared after |
| The token is the credential | Wrong token 404s, right token works, tracking survives the shop closing |
| Claiming | Claim attaches and spends the token, replay is refused, claiming needs a session |
| Merging | Guest cart merges on sign-in, quantities add, cap at available stock |
| Cart ownership | One guest cannot edit another's basket, no token owns no cart, malformed cookie treated as absent, browsing creates no cart |
| The service | Posted guest fields ignored for a signed-in buyer, feature refusal, email match |

### 3.1 Verified in both directions

| Defect reintroduced | Result |
| --- | --- |
| `locate()` ignores the token and matches on reference alone | `test_an_order_cannot_be_read_without_its_token` fails |
| The claim leaves the token live after use | `test_a_claim_token_cannot_be_replayed` fails |

Restored from backup and re-run green.

### 3.2 A test that was encoding the defect

`OrderHttpTest::test_cart_and_checkout_require_auth` asserted that a guest is
redirected to `/login` — the exact behaviour §17 calls wrong and this phase
exists to reverse. It was rewritten to assert the intended behaviour rather than
deleted, so the file still covers that surface.

### 3.3 A test-harness choice worth recording

`disableCookieEncryption()` would have made the cart-token round trip trivial,
and would have stopped exercising `EncryptCookies` — the middleware every real
request goes through. The tests instead read the cookie decrypted
(`getCookie()` decrypts by default) and send it back through `withCookie()`,
which re-encrypts: the round trip a browser actually makes.

## 4. Commands run and exact results

```
$ ./vendor/bin/phpunit --filter GuestCheckoutTest
OK (19 tests, 65 assertions)

$ ./vendor/bin/phpunit
Tests: 1633, Assertions: 4856, Skipped: 13        # 0 failures, 0 errors

$ bash scripts/testing/run-mariadb-tests.sh        # MariaDB 11.4.2, port 3399
Tests: 1633, Assertions: 4866, Skipped: 7          # 0 failures, 0 errors
```

Migration verified up, down and up again on both engines:

```
$ DB_CONNECTION=sqlite ... migrate / migrate:rollback --step=1 / migrate   → DONE, DONE, DONE
# MariaDB 11.4.2 scratch database: same. Resulting schema:
  `user_id` bigint(20) unsigned DEFAULT NULL
  `guest_token` varchar(64) DEFAULT NULL
```

`storefront_carts.user_id` was widened, never narrowed. `down()` deliberately
leaves it nullable: narrowing it again would reject any guest cart written while
the migration was applied.

SQLite cannot alter a column in place, so the table is rebuilt there. The
rebuild restores the canonical index names afterwards — SQLite index names are
global, so the copy had to use temporary ones while both tables existed, and
leaving them would have meant `down()` looking for indexes under names the rest
of the schema does not use.

## 5. Carried forward

| Item | Where it belongs |
| --- | --- |
| Inventory hold before the Paystack redirect (§38 "availability") | Phase 10 — that is the phase that builds holds |
| Tax line on the checkout summary (§38 "show subtotal, delivery, tax, total") | Phase 12 — storefront tax calculation arrives with the finance integration; a tax figure that disagreed with what the order posts would be worse than none |
| `storefront.customer_accounts` enforcement | Phase 14 |
| Abandoned guest carts are never swept | Phase 15 or an operational job. They are harmless rows, but they accumulate |

## 6. Status

**Phase 9: COMPLETE.** Phases 8 and 9 of the plan are done; Phase 10 (checkout
holds and reservations) is next and now unblocked.
