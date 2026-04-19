# Product Type Support — Design

**Date:** 2026-04-18
**Status:** Approved, pending implementation plan

## Goal

Extend the seeder so `db:seed --generate` produces realistic Magento products of all five standard types: simple, configurable, bundle, grouped, downloadable.

## Non-goals

- Custom product types beyond the five above.
- Virtual products (can be added trivially later — same flow as simple with `is_virtual=1`).
- Creating missing EAV attributes for configurable variants. If `color` or `size` is missing, we skip configurable generation with a warning rather than mutating the target install.

## CLI

Extend the `--generate` parser to accept dotted subtypes for products:

```
bin/magento db:seed --generate=product:100
bin/magento db:seed --generate=product.configurable:20,product.bundle:10
bin/magento db:seed --generate=product:100,product.bundle:20
```

- `product:N` uses a weighted random split of 70/10/10/5/5 across simple/configurable/bundle/grouped/downloadable.
- `product.<subtype>:N` forces every one of those N iterations to the given subtype.
- Explicit subtype counts **add** to `product:N`; they do not replace it.

The split weights live as a constant in the parser so tuning is a one-line change.

## Architecture

Keep the existing single-generator / single-handler-per-entity shape. Introduce a builder-per-subtype strategy inside the product handler:

```
ProductDataGenerator (one) ──► writes 'product_type' + subtype-specific fields
                                                │
ProductHandler (one) ───────────► ProductTypeBuilderPool
                                      ├─ SimpleBuilder
                                      ├─ ConfigurableBuilder
                                      ├─ BundleBuilder
                                      ├─ GroupedBuilder
                                      └─ DownloadableBuilder
```

The handler still owns cross-cutting behavior (stock, website assignment, image gallery, stock reindex). Each builder owns type-specific setup (variation attributes, option links, link files) performed on the `$product` object before the shared save path runs.

## Data shape

`ProductDataGenerator::generate()` returns:

```php
[
    'product_type' => 'simple' | 'configurable' | 'bundle' | 'grouped' | 'downloadable',
    'sku'          => 'SEED-...',
    'name'         => '...',
    'price'        => 12.34,
    // ...existing simple fields...

    // subtype-specific (present only for the relevant type):
    'configurable' => [
        'attributes' => ['color', 'size'],
        // children are built automatically by the builder at create time
    ],
    'bundle' => [
        'options' => [
            ['title' => 'Size', 'type' => 'select', 'required' => true, 'children' => 2],
            // ...
        ],
    ],
    'grouped' => [
        'linked_count' => 5,
    ],
    'downloadable' => [
        'links' => [
            ['title' => '...', 'sample_text' => '...'],
            // ...
        ],
    ],
]
```

The generator itself does not look up or create child products — that's the builder's job, because it runs in the Magento context (DB access, repositories) that the data generator deliberately avoids.

## Child product sourcing

Bundle and grouped need existing simples. Priority order:

1. `GeneratedDataRegistry::getAll('product')` — prefer in-run-memory simples.
2. Load recent `SEED-%` SKUs from `catalog_product_entity` — reuse prior-run data.
3. Create N hidden simples inline (silent, not counted in user totals).

Configurable always creates its own 6 children (3 colors × 2 sizes) because attribute combinations matter. Children get `visibility = NOT_VISIBLE_INDIVIDUALLY`, derived SKUs (`PARENT-red-S`), and inherit parent price.

## Attribute discovery (configurable)

On first configurable build per run, look up `color` and `size` attributes via `EavConfig::getAttribute('catalog_product', 'color')`. If either is missing or has no options:

- Log a warning via the existing logger.
- Mark configurable as unavailable for the remainder of the run; any subsequent `product.configurable` iterations fail fast with a clear message; weighted `product:N` runs skip the configurable slot and redistribute to simple.

We deliberately do not create attributes. That belongs in a Magento data patch, not in a seeder.

## Downloadable files

Builder generates a ~200-byte plaintext sample per link using Faker paragraph output. Writes to `pub/media/downloadable/files/seed-<hash>.txt` and `pub/media/downloadable/files_sample/seed-<hash>-sample.txt`. Attached via `\Magento\Downloadable\Api\Data\LinkInterface` with `link_type = 'file'`.

## Failure handling

Per-iteration failures continue to flow through the existing `GenerateRunner::generateType` path (already tracks `failed` count, surfaces in CLI output, exits non-zero). Each builder raises `\RuntimeException` on unrecoverable state so the runner logs and moves on. The `--stop-on-error` flag still short-circuits.

## Tests

- Unit: one test file per builder (`SimpleBuilderTest`, `ConfigurableBuilderTest`, `BundleBuilderTest`, `GroupedBuilderTest`, `DownloadableBuilderTest`) — mock Magento collaborators, assert the right setter calls and save sequencing.
- Unit: `ProductDataGeneratorTest` updated to assert `product_type` presence and subtype-specific payload shape.
- Unit: `SeedCommandTest` updated with the dotted-subtype parser cases.
- Integration: post-implementation smoke run against `mage-os-typesense`:
  - Seed one of each subtype, query DB:
    - `catalog_product_super_link` populated for configurable.
    - `catalog_product_bundle_option` + `catalog_product_bundle_selection` populated for bundle.
    - `catalog_product_link` (type 3 = grouped) populated for grouped.
    - `downloadable_link` + sample file exists on disk for downloadable.
  - Admin UI loads the product edit page for each without errors.
  - Frontend renders the product page without a type-handler fatal.

## Files (rough sketch)

New:
- `src/EntityHandler/Product/TypeBuilderInterface.php`
- `src/EntityHandler/Product/TypeBuilderPool.php`
- `src/EntityHandler/Product/TypeBuilder/SimpleBuilder.php`
- `src/EntityHandler/Product/TypeBuilder/ConfigurableBuilder.php`
- `src/EntityHandler/Product/TypeBuilder/BundleBuilder.php`
- `src/EntityHandler/Product/TypeBuilder/GroupedBuilder.php`
- `src/EntityHandler/Product/TypeBuilder/DownloadableBuilder.php`

Modified:
- `src/DataGenerator/ProductDataGenerator.php` — emits `product_type` + subtype payload.
- `src/EntityHandler/ProductHandler.php` — delegate subtype work to the pool; keep stock/image/indexing shared.
- `src/Console/Command/SeedCommand.php` — parse `product.<subtype>:N`, feed it through config.
- `src/Service/GenerateRunConfig.php` / `GenerateRunner.php` — propagate subtype hint (if needed) so the data generator knows whether a forced subtype is active.
- `src/etc/di.xml` — wire the builder pool, add new composer deps.
- `composer.json` — add `magento/module-configurable-product`, `magento/module-bundle`, `magento/module-grouped-product`, `magento/module-downloadable` to `require`.

## Order state support (added 2026-04-18)

The generator also produces orders in a mix of Magento states so dev stores feel populated. Supported states: `new`, `processing`, `complete`, `canceled`, `holded`, `closed`. Skipped: `pending_payment`, `payment_review` (require payment-method plumbing; low dev value).

### CLI

Mirrors the product-type syntax:

```
bin/magento db:seed --generate=order:100                   # weighted split
bin/magento db:seed --generate=order.complete:50,order.canceled:10
bin/magento db:seed --generate=order:100,order.holded:5    # combine
```

Default weights: 15/25/40/10/5/5 across new/processing/complete/canceled/holded/closed.

### Architecture

Same shape as product types — extract a strategy pool:

```
OrderDataGenerator ──► writes 'order_state' + state-specific params
OrderHandler ────► places the order (unchanged) ──► OrderStateTransitionPool
                                                       ├─ NewTransition           (no-op)
                                                       ├─ ProcessingTransition    (invoice)
                                                       ├─ CompleteTransition      (invoice + shipment)
                                                       ├─ CanceledTransition      (cancel)
                                                       ├─ HoldedTransition        (hold)
                                                       └─ ClosedTransition        (invoice + credit memo)
```

### Transitions (how Magento gets to each state)

- `new`: no-op.
- `processing`: `InvoiceService::prepareInvoice()` → register → save via `InvoiceRepository`. Payment captures offline (checkmo).
- `complete`: processing steps, then build a shipment via `ShipmentFactory::create()` with all items → save via `ShipmentRepository`.
- `canceled`: `Order::cancel()` then save via `OrderRepository`.
- `holded`: `Order::hold()` then save via `OrderRepository`.
- `closed`: invoice, then `CreditmemoFactory::createByOrder()` + `CreditmemoService::refund($memo, true)` for a full offline refund.

### Failure handling

A transition throwing does not bring down the run. The order still exists in state `new`; the transition failure is logged and counted toward the order type's `failed` count. Non-invoiceable orders (empty cart edge cases) skip gracefully.

### Tests

- Unit: one test file per transition strategy (6 files, mocking `InvoiceService`, `ShipmentFactory`, `CreditmemoService`, `OrderRepository` etc).
- Unit: `OrderDataGeneratorTest` asserts `order_state` is emitted and forced when `setForcedSubtype()` is called.
- Integration: `bin/magento db:seed --generate=order:50 --seed=X` produces orders in at least 4 distinct states; DB query on `sales_order.state` confirms the distribution roughly matches weights.

### Files (orders addendum)

New:
- `src/EntityHandler/Order/StateTransitionInterface.php`
- `src/EntityHandler/Order/StateTransitionPool.php`
- `src/EntityHandler/Order/StateTransition/NewTransition.php`
- `src/EntityHandler/Order/StateTransition/ProcessingTransition.php`
- `src/EntityHandler/Order/StateTransition/CompleteTransition.php`
- `src/EntityHandler/Order/StateTransition/CanceledTransition.php`
- `src/EntityHandler/Order/StateTransition/HoldedTransition.php`
- `src/EntityHandler/Order/StateTransition/ClosedTransition.php`

Modified:
- `src/EntityHandler/OrderHandler.php` — call pool after `placeOrder`.
- `src/DataGenerator/OrderDataGenerator.php` — emits `order_state` via weighted random or forced subtype.
- `src/Service/GenerateRunner.php` — already subtype-aware from Task 12; same mechanism applies to `order.*`.
- `src/etc/di.xml` — register the pool.

## Open follow-ups (deliberately out of scope)

- Tuning the 70/10/10/5/5 split or letting the user pass weights.
- Per-subtype image counts (configurable could get multiple images; skipped for v1).
- Customer-group prices, tier prices.
- Additional bundle option types beyond select/radio/checkbox.
