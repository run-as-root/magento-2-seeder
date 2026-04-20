# Cart Rules, Wishlists, Newsletter Subscribers — Design

**Date:** 2026-04-20
**Status:** Approved, pending implementation plan

## Goal

Add three new entity types to the generator so dev stores look realistically populated beyond just catalog + customers + orders:

1. `cart_rule` — sales rules with an attached specific coupon.
2. `wishlist` — one wishlist per customer, each with a small item list.
3. `newsletter_subscriber` — mix of subscribed customers and guest emails.

## Non-goals

- Catalog price rules (separate subsystem, low dev value).
- Complex rule conditions / actions trees (attribute-based, SKU filters, nested OR/AND). MVP applies to all carts.
- Multiple wishlists per customer (Magento B2B / MP feature).
- Subscriber confirmation flow / double opt-in — we write directly as `STATUS_SUBSCRIBED`.
- Email sending. Nothing in this design dispatches mail.

## Architecture

Same shape as every existing type. Each of the three becomes a pair:

```
DataGenerator/CartRuleDataGenerator.php          ──► EntityHandler/CartRuleHandler.php
DataGenerator/WishlistDataGenerator.php          ──► EntityHandler/WishlistHandler.php
DataGenerator/NewsletterSubscriberDataGenerator  ──► EntityHandler/NewsletterSubscriberHandler.php
```

Both pools (`DataGeneratorPool`, `EntityHandlerPool`) pick them up via DI in `src/etc/di.xml`. No runner or config changes required — the pattern already supports arbitrary types.

## Type metadata

| Type                     | Order | Deps                | Dependency counts                      |
|--------------------------|-------|---------------------|----------------------------------------|
| `cart_rule`              | 50    | none                | n/a                                    |
| `wishlist`               | 60    | `customer`, `product` | 1:1 customer (one wishlist per customer); products sampled |
| `newsletter_subscriber`  | 70    | none (customer optional) | n/a — half sample from registry, half fresh guests |

`wishlist.getDependencyCount('customer', N) = N` so auto-resolution seeds enough customers when the user asks for `wishlist:N` alone. `product` count stays untouched (the wishlist reuses whatever products exist; if fewer than expected, the generator picks what it can and skips the shortfall).

Newsletter does **not** declare `customer` as a dependency — we tolerate zero customers (all guests) and we don't want to auto-inflate customer counts for a newsletter-only run.

## Data shapes

### `cart_rule`

```php
[
    'name'              => 'Seed Rule — Summer Sale',
    'description'       => '…',
    'is_active'         => 1,
    'website_ids'       => [1],
    'customer_group_ids'=> [0, 1, 2, 3],     // NOT LOGGED IN + General + Wholesale + Retailer
    'from_date'         => null,
    'to_date'           => '+1 year',        // ISO date string resolved in handler
    'uses_per_customer' => 0,
    'simple_action'     => 'by_percent' | 'by_fixed' | 'free_shipping',
    'discount_amount'   => 10.0,             // percent (5–30) or fixed (5–50); ignored for free_shipping
    'discount_qty'      => 0,
    'stop_rules_processing' => 0,
    'sort_order'        => 0,
    'conditions'        => [],               // empty serialized tree = apply to all
    'actions'           => [],
    'coupon' => [
        'type'  => 'specific_coupon',        // => coupon_type = SPECIFIC
        'code'  => 'SAVE10-AB12CD',          // auto-generated
        'uses_per_coupon' => 0,
    ],
]
```

Action mix weights: `by_percent` 60 / `by_fixed` 30 / `free_shipping` 10.

Coupon code format: `<PREFIX><NN>-<RAND6>` where `PREFIX` is one of `SAVE`, `DEAL`, `PROMO`, `BONUS` (tracks the action) and `RAND6` is an uppercase alnum token. Collisions are vanishingly unlikely at dev-data scale; the handler catches the (rare) duplicate exception and retries once with a new tail.

### `wishlist`

```php
[
    'customer_id' => 42,
    'shared'      => 0,
    'items' => [
        ['product_id' => 101, 'qty' => 1],
        ['product_id' => 117, 'qty' => 1],
        // 1..5 items
    ],
]
```

If fewer than 1 customer or 1 product exist in the registry, generator throws a clean exception — the runner logs and moves on. Never silently produces empty wishlists.

### `newsletter_subscriber`

```php
[
    'email'             => 'jane@example.com',
    'store_id'          => 1,
    'subscriber_status' => 1,   // STATUS_SUBSCRIBED
    'customer_id'       => 42,  // or 0 for guests
]
```

50/50 split via `faker->boolean(50)`. When "linked", pulls a random customer id + email from the registry; falls back to guest if no customers registered yet (no hard error).

## Handlers

### `CartRuleHandler`

- Uses `Magento\SalesRule\Api\RuleRepositoryInterface` + `Magento\SalesRule\Api\Data\RuleInterfaceFactory`.
- Resolves relative dates (`+1 year`) with `DateTimeImmutable` into ISO strings.
- Empty conditions → `simple_action` rule condition of `'all' 'TRUE'` (Magento's conventional "match everything") serialized via the standard combine model. If setting an empty conditions array trips up the rule save, we fall back to the same minimal serialized tree used in `SalesRule/Setup/Patch/Data/*`.
- Coupon: sets `coupon_type = COUPON_TYPE_SPECIFIC` and `coupon_code` via the rule's primary coupon model; saved in the same call.
- `clean()`: `searchCriteria` with `name LIKE 'Seed Rule — %'`, delete via repository.

### `WishlistHandler`

- Uses `Magento\Wishlist\Model\WishlistFactory` + `WishlistResource` (Magento's public API here is thin).
- `$wishlist->loadByCustomerId($customerId, true)` creates-or-loads. `$wishlist->addNewItem($product)` for each product.
- `setShared(0)`.
- `clean()`: `DELETE FROM wishlist WHERE customer_id IN (SELECT entity_id FROM customer_entity WHERE email LIKE '%@example.%')`. Rows in `wishlist_item` cascade via the existing FK. Scoped to seed-style emails so real wishlist data on the instance is untouched.

### `NewsletterSubscriberHandler`

- Uses `Magento\Newsletter\Model\SubscriberFactory` + `SubscriberResource`.
- For linked subscribers, calls `$subscriber->loadByEmail($email)` first so we don't violate the unique-email constraint if the customer already had a row.
- Sets `subscriber_status = Subscriber::STATUS_SUBSCRIBED`, `store_id`, `customer_id` (or `0`).
- `clean()`: delete all rows where `subscriber_email LIKE '%@example.%'` OR tagged `change_status_at IS NULL AND customer_id = 0` (guest seeds) — narrow enough to avoid real subscribers, broad enough to reclaim guest seeds. For linked ones, clean when the customer cleans (cascade already exists on the `newsletter_subscriber.customer_id` FK in core? — **verify during implementation**, fall back to explicit delete by customer_id if not).

## Address tweak (piggyback)

`CustomerDataGenerator` currently emits one address. Bump to 1–3 addresses:

- Index 0 stays default billing + shipping (as today).
- Additional addresses (1–2 more, random) are non-default, same structure.
- No handler change — `CustomerHandler::create` already loops `$data['addresses']`.

## DI registration

`src/etc/di.xml` grows three entries in `DataGeneratorPool` constructor arg + three in `EntityHandlerPool`. Pattern already established by the existing five types.

## Composer dependencies

Add to `composer.json` `require`:

- `magento/module-sales-rule`
- `magento/module-wishlist`
- `magento/module-newsletter`

## Failure handling

Per-iteration exceptions flow through the existing `GenerateRunner::generateType` path (tracked as `failed`, surfaced in CLI, respects `--stop-on-error`). No new bespoke handling.

## Tests

Per existing convention (final classes, snake_case test methods):

- Unit: `CartRuleDataGeneratorTest`, `WishlistDataGeneratorTest`, `NewsletterSubscriberDataGeneratorTest` — assert shape and action/status distribution given a seeded Faker.
- Unit: coupon code generator test — uniqueness retry path.
- Integration: extend `Test/Integration/SeederFacadeSmokeTest` (or a parallel file) to run `--generate=cart_rule:5,wishlist:5,newsletter_subscriber:10` end-to-end against a Warden/`mage-os-typesense` env, then assert row counts in `salesrule`, `salesrule_coupon`, `wishlist`, `wishlist_item`, `newsletter_subscriber`.
- Clean idempotency: run generate → clean → generate again, assert no duplicate-key blowups.

## Files (rough sketch)

New:
- `src/DataGenerator/CartRuleDataGenerator.php`
- `src/DataGenerator/WishlistDataGenerator.php`
- `src/DataGenerator/NewsletterSubscriberDataGenerator.php`
- `src/EntityHandler/CartRuleHandler.php`
- `src/EntityHandler/WishlistHandler.php`
- `src/EntityHandler/NewsletterSubscriberHandler.php`
- `Test/Unit/DataGenerator/CartRuleDataGeneratorTest.php`
- `Test/Unit/DataGenerator/WishlistDataGeneratorTest.php`
- `Test/Unit/DataGenerator/NewsletterSubscriberDataGeneratorTest.php`

Modified:
- `src/DataGenerator/CustomerDataGenerator.php` — emit 1–3 addresses.
- `src/etc/di.xml` — register three new generator/handler pairs.
- `composer.json` — add three Magento modules.
- `Test/Integration/SeederFacadeSmokeTest.php` — extend assertions (or add sibling test file).

## Open follow-ups (out of scope)

- Attribute-based or SKU-based cart rule conditions.
- Catalog price rules.
- Wishlist sharing (`shared=1`, share_mails).
- Subscriber mixed statuses (pending, unsubscribed) — useful for email-platform testing, not for populating a dev store.
- Linking cart rules to specific customers (uses_per_customer targeting).
