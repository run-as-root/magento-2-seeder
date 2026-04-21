# Magento 2 Database Seeder

[![CI](https://github.com/run-as-root/magento-2-seeder/actions/workflows/ci.yml/badge.svg)](https://github.com/run-as-root/magento-2-seeder/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

Laravel-style database seeding for Magento 2 / Mage-OS. Define simple PHP / JSON / YAML files (or use the built-in Faker generators), run `bin/magento db:seed`, populate your dev environment with realistic products, categories, customers, orders, CMS content, reviews, cart rules, wishlists, and newsletter subscribers.

![db:seed --generate=order:10 --fresh](docs/demo/seed-orders.gif)

## Installation

```bash
composer require runasroot/module-seeder --dev
bin/magento module:enable RunAsRoot_Seeder
bin/magento setup:upgrade
```

## Quick Start

1. Scaffold a seeder: `bin/magento db:seed:make`
2. Run `bin/magento db:seed`

## Usage

```bash
# Run all seeders
bin/magento db:seed

# Run only specific types
bin/magento db:seed --only=customer,order

# Skip specific types
bin/magento db:seed --exclude=cms

# Wipe relevant data and re-seed
bin/magento db:seed --fresh

# Stop on first error
bin/magento db:seed --stop-on-error

# Combine flags
bin/magento db:seed --fresh --only=customer,product

# Show current DB counts of seeded entities
bin/magento db:seed:status
```

## Scaffolding

`db:seed:make` creates a seeder file for you — no need to memorize the format.

```bash
# Interactive
bin/magento db:seed:make

# Flag-driven (CI / scripts)
bin/magento db:seed:make --type=order --count=100 --format=php

# Overwrite an existing file
bin/magento db:seed:make --type=order --count=100 --force
```

Available flags:

| Flag | Default | Notes |
|------|---------|-------|
| `--type` | — | required non-interactive |
| `--count` | — | required non-interactive |
| `--format` | `php` | `php` / `json` / `yaml` |
| `--name` | `{Type}Seeder` | file name without extension |
| `--locale` | `en_US` | Faker locale |
| `--seed` | random | Faker seed for deterministic output |
| `--force` | `false` | overwrite existing file |

## Seeder Formats

Seeder files live in `dev/seeders/` and must end in `Seeder.<ext>`. Three formats are supported:

| Format | Extension         | Use when                                            |
|--------|-------------------|-----------------------------------------------------|
| PHP    | `.php`            | Array or class with loops / Faker / conditionals    |
| JSON   | `.json`           | Machine-generated fixtures or cross-language tools  |
| YAML   | `.yaml` / `.yml`  | Human-readable fixtures                             |

All three share the same payload shape: `type`, `data` (or `count`), and optional `order`/`locale`/`seed`.

### Array-Based (PHP)

Create a PHP file ending in `Seeder.php` that returns an array with `type` and `data`:

```php
<?php
// dev/seeders/CustomerSeeder.php
return [
    'type' => 'customer',
    'data' => [
        ['email' => 'john@test.com', 'firstname' => 'John', 'lastname' => 'Doe', 'password' => 'Test1234!'],
        ['email' => 'jane@test.com', 'firstname' => 'Jane', 'lastname' => 'Doe', 'password' => 'Test1234!'],
    ],
];
```

### JSON

```json
// dev/seeders/CustomerSeeder.json
{
    "type": "customer",
    "data": [
        {"email": "john@test.com", "firstname": "John", "lastname": "Doe", "password": "Test1234!"},
        {"email": "jane@test.com", "firstname": "Jane", "lastname": "Doe", "password": "Test1234!"}
    ]
}
```

### YAML

```yaml
# dev/seeders/CustomerSeeder.yaml
type: customer
data:
  - email: john@test.com
    firstname: John
    lastname: Doe
    password: Test1234!
  - email: jane@test.com
    firstname: Jane
    lastname: Doe
    password: Test1234!
```

Invalid JSON/YAML files are logged to `var/log/` and skipped; the rest of the run continues.

### Class-Based (fluent, recommended)

For complex scenarios, extend `RunAsRoot\Seeder\Seeder` and use the fluent builder:

```php
<?php
// dev/seeders/MassOrderSeeder.php
use RunAsRoot\Seeder\Seeder;

class MassOrderSeeder extends Seeder
{
    public function getType(): string { return 'order'; }
    public function getOrder(): int { return 40; }

    public function run(): void
    {
        $this->orders()
            ->count(50)
            ->with(['items' => [['sku' => 'TSHIRT-001', 'qty' => 2]]])
            ->create();
    }
}
```

Available builder entry points: `customers()`, `products()`, `orders()`, `categories()`, `cms()`, plus `seed('custom_type')` for types registered via `di.xml`.

Builder methods:

| Method | Purpose |
|---|---|
| `->count(int $n)` | How many to create |
| `->with(array $data)` | Static overrides merged into each iteration (shallow replace) |
| `->using(callable $fn)` | Per-iteration callback: `fn(int $i, Faker\Generator $faker): array` |
| `->subtype(string $s)` | Force subtype (e.g. `'bundle'` for products, `'complete'` for orders) |
| `->create()` | Executes and returns `int[]` of created ids |

Precedence (most specific wins): `using()` > `with()` > generator Faker defaults.

If your subclass needs its own dependencies, override the constructor and call `parent::__construct(...)`:

```php
public function __construct(
    EntityHandlerPool $handlers,
    DataGeneratorPool $generators,
    FakerFactory $fakerFactory,
    GeneratedDataRegistry $registry,
    private readonly MyService $svc,
) {
    parent::__construct($handlers, $generators, $fakerFactory, $registry);
}
```

### Class-Based (low-level)

If you need full control, implement `SeederInterface` directly and inject `EntityHandlerPool`:

```php
class CustomSeeder implements SeederInterface
{
    public function __construct(private readonly EntityHandlerPool $handlerPool) {}
    public function getType(): string { return 'order'; }
    public function getOrder(): int { return 40; }
    public function run(): void
    {
        $this->handlerPool->get('order')->create([...]);
    }
}
```

## Supported Entity Types

| Type                    | What it creates                                        |
|-------------------------|--------------------------------------------------------|
| `customer`              | Customer accounts                                      |
| `category`              | Category tree nodes                                    |
| `product`               | Products (all five Magento types)                      |
| `order`                 | Orders via quote-to-order flow                         |
| `cms`                   | CMS pages and blocks                                   |
| `cart_rule`             | Shopping-cart price rules with a specific coupon each  |
| `wishlist`              | Wishlists with 1–5 product items per seeded customer   |
| `newsletter_subscriber` | Newsletter subscribers (50/50 linked customers vs guests) |

## Default Seeding Order

1. Categories (10)
2. Products (20)
3. Customers (30)
4. Orders (40)
5. CMS / Cart Rules (50)
6. Wishlists (60)
7. Newsletter Subscribers (70)

Override with `'order' => 5` in array seeders or `getOrder(): int` in class seeders.

## The `--fresh` Flag

When using `--fresh`, the module cleans existing data before seeding:

- **Customers**: Deletes all non-admin customers
- **Products**: Deletes all products
- **Categories**: Deletes all categories except root (ID 1) and default (ID 2)
- **Orders**: Deletes all orders (FK cascades handle invoices, shipments, etc.)
- **CMS**: Only deletes pages/blocks with the `seed-` identifier prefix
- **Cart Rules**: Only deletes rules whose name starts with `Seed Rule — ` (attached coupons cascade)
- **Wishlists**: Only deletes wishlists whose owner's email matches `%@example.%` (items cascade via FK)
- **Newsletter Subscribers**: Only deletes rows whose email matches `%@example.%`

Clean runs in reverse dependency order (later-ordered types first).

## Extending

Add custom entity handlers via `di.xml`:

```xml
<type name="RunAsRoot\Seeder\Service\EntityHandlerPool">
    <arguments>
        <argument name="handlers" xsi:type="array">
            <item name="custom_entity" xsi:type="object">Vendor\Module\Seeder\CustomEntityHandler</item>
        </argument>
    </arguments>
</type>
```

Your handler must implement `RunAsRoot\Seeder\Api\EntityHandlerInterface`.

## Data Generation with Faker

Generate realistic fake data at scale using the `--generate` flag. No seeder files needed.

### Basic Usage

```bash
# Generate 1000 orders
bin/magento db:seed --generate=order:1000

# Generate multiple entity types
bin/magento db:seed --generate=order:1000,customer:500

# Use a specific locale
bin/magento db:seed --generate=customer:100 --locale=de_DE

# Deterministic output (same seed = same data)
bin/magento db:seed --generate=product:50 --seed=42

# Combine with --fresh to wipe and regenerate
bin/magento db:seed --generate=order:500 --fresh
```

### Progress bar

When a resolved count for any type is **10 or more**, the command renders a per-type
Symfony Console progress bar so long runs show live progress. Smaller counts keep the
compact `Generated N type(s)... done` output. Nothing to configure.

### Smart Dependency Resolution

You only need to request the entities you want. Dependencies are auto-generated with sensible ratios.

For example, `--generate=order:1000` will also generate the required customers, products, and categories automatically.

| Requested                    | Auto-generates                                                                          |
|------------------------------|-----------------------------------------------------------------------------------------|
| `order:1000`                 | `customer:200` (1:5 ratio), `product:50` (1:20 ratio), `category:10` (1:5 of products)  |
| `product:100`                | `category:20` (1:5 ratio)                                                               |
| `wishlist:50`                | `customer:50` (1:1 ratio); products reused from whatever's in the DB/registry           |
| `customer:500`               | Nothing (no dependencies)                                                               |
| `category:50`                | Nothing (no dependencies)                                                               |
| `cms:20`                     | Nothing (no dependencies)                                                               |
| `cart_rule:20`               | Nothing (no dependencies)                                                               |
| `newsletter_subscriber:100`  | Nothing — links to customers already in the registry, falls back to guest emails       |

If you explicitly request a dependency type, your count takes precedence over the auto-calculated one.

### Count-Based Seeder Files

Instead of listing individual data entries, use the `count` key to generate Faker data from a seeder file:

```php
<?php
// dev/seeders/GenerateOrderSeeder.php
return [
    'type' => 'order',
    'count' => 100,
    'locale' => 'en_US',
];
```

This triggers the Faker generation pipeline (with dependency resolution) instead of the standard array-based data flow.

## Product Reviews

Every seeded product automatically gets **0–10 reviews** with Faker-generated nicknames, titles, details, and a 1–5 star rating. Reviews are created against the default store (id 1) with status `Approved` so they render on the frontend immediately.

No CLI flag required — reviews are part of the product seed payload. If you want to disable reviews temporarily, set the `reviews` count range in `src/DataGenerator/ProductDataGenerator.php` (`generateReviews()` helper).

## Product Types

The seeder supports all five standard Magento product types. Plain `--generate=product:N` produces a weighted mix; dotted subtypes force a specific type.

### CLI

```
bin/magento db:seed --generate=product:100
bin/magento db:seed --generate=product.configurable:20,product.bundle:10
bin/magento db:seed --generate=product:100,product.bundle:20  # mix + force
```

### Default weights (for plain `product:N`)

| Subtype       | Weight |
|---------------|-------:|
| simple        |    70% |
| configurable  |    10% |
| bundle        |    10% |
| grouped       |     5% |
| downloadable  |     5% |

Change weights in `src/DataGenerator/ProductDataGenerator.php` — `SUBTYPE_WEIGHTS` constant.

### Per-type behavior

- **Simple**: as before.
- **Configurable**: auto-creates 6 hidden simple children spanning 3 color options × 2 size options. **Requires** `color` and `size` attributes with option values on the target install — if either is missing or empty, configurable generation fails fast with a clear error. The module does not create attributes.
- **Bundle**: creates a dynamic-price bundle with up to 3 options (select / radio / checkbox), each linking 2–3 existing simples. Falls back from registry → SEED-% products in DB → warns and skips if the simple pool is empty.
- **Grouped**: links up to 5 existing simples via `catalog_product_link` (link type 3). Same fallback chain as bundle.
- **Downloadable**: attaches 1–2 file-type links backed by Faker-generated `.txt` samples under `pub/media/downloadable/files/` and `pub/media/downloadable/files_sample/`.

### Category distribution

Products are assigned to the category with the fewest products so far (ties go to the earliest category). As long as the run produces at least as many products as categories, every category ends up with at least one product.

### Custom Data Generators

Add your own data generators via `di.xml`:

```xml
<type name="RunAsRoot\Seeder\Service\DataGeneratorPool">
    <arguments>
        <argument name="generators" xsi:type="array">
            <item name="custom_entity" xsi:type="object">Vendor\Module\Seeder\CustomEntityDataGenerator</item>
        </argument>
    </arguments>
</type>
```

Your generator must implement `RunAsRoot\Seeder\Api\DataGeneratorInterface`.

## Order States

Orders are generated across the real Magento lifecycle states. Plain `--generate=order:N` produces a weighted mix; dotted subtypes force a specific state.

### CLI

```
bin/magento db:seed --generate=order:100
bin/magento db:seed --generate=order.complete:50,order.canceled:10
bin/magento db:seed --generate=order:100,order.holded:5  # mix + force
```

### Default state weights

| State        | Weight |
|--------------|-------:|
| new          |    15% |
| processing   |    25% |
| complete     |    40% |
| canceled     |    10% |
| holded       |     5% |
| closed       |     5% |

Change weights in `src/DataGenerator/OrderDataGenerator.php` — `STATE_WEIGHTS` constant. States `pending_payment` and `payment_review` are intentionally skipped; they require payment-gateway plumbing and add little dev-data value.

### Per-state behavior

- **new**: default state after `CartManagementInterface::placeOrder`. No additional action.
- **processing**: order is invoiced offline (`InvoiceService::prepareInvoice` + `register` with `CAPTURE_OFFLINE`).
- **complete**: invoice (as above), then full shipment via `ShipmentFactory::create($order, $itemQtyMap)` + `register`.
- **canceled**: `$order->cancel()`.
- **holded**: `$order->hold()`.
- **closed**: invoice, then offline refund via `CreditmemoFactory::createByOrder` + `CreditmemoManagementInterface::refund($memo, true)`.

After each invoice-based transition, the order state is explicitly set and saved a second time because some Magento observer chains reset the state during the transaction save. Without this, invoiced orders would remain stuck at `new`.

### Order items

Seeded orders only use **simple** products as cart items — bundles, configurables, grouped, and downloadables require per-cart option selections that would complicate the seeder. `OrderDataGenerator` declares `product.simple` as its product dependency, so the dependency resolver always produces the right type.

## Cart Rules

Plain `--generate=cart_rule:N` creates N sales rules, each with one attached manual-code coupon.

### CLI

```
bin/magento db:seed --generate=cart_rule:20
```

### Action mix

| simple_action   | Weight | Discount amount              | Coupon prefix |
|-----------------|-------:|-----------------------------|---------------|
| `by_percent`    |   60%  | 5–30 (percent off)           | `SAVE`        |
| `by_fixed`      |   30%  | 5–50 (fixed currency off)    | `DEAL`        |
| `free_shipping` |   10%  | n/a (sets FREE_SHIPPING_ITEM)| `PROMO`       |

Change weights in `src/DataGenerator/CartRuleDataGenerator.php` — `ACTION_WEIGHTS` constant.

### Rule shape

- Active for all websites (id 1) and all four default customer groups (NOT LOGGED IN + General + Wholesale + Retailer).
- Empty conditions tree — applies to every cart.
- `to_date` = today + 1 year. No `from_date` restriction.
- Rule name: `Seed Rule — <two Faker words>` so `--fresh` can scope cleanup.
- Coupon code: `<PREFIX><amount>-<6 uppercase alnum>`, e.g. `SAVE12-AB1234`. Collision retry with a random suffix, up to 3 attempts.

## Wishlists

Plain `--generate=wishlist:N` creates one wishlist per seeded customer, each with 1–5 randomly-picked products.

### CLI

```
bin/magento db:seed --generate=wishlist:50
```

Requires at least 1 customer and 1 product in the registry (auto-generated per the dependency resolver — see the table above).

### Items

- `qty` = 1, `shared` = 0.
- Items are inserted directly into `wishlist_item`, bypassing `Wishlist::addNewItem`'s stock guard. This keeps the seeder resilient to freshly-seeded products whose stock index hasn't caught up yet. Tradeoff: only simple products are exercised today — configurable / bundle wishlist items would need the full `addNewItem` option-serialization path.

### Cleanup

`--fresh` scopes wishlist cleanup to customers whose email matches `%@example.%` (Faker's `safeEmail()` domain). `wishlist_item` rows cascade via FK.

## Newsletter Subscribers

Plain `--generate=newsletter_subscriber:N` creates N subscriber rows with a roughly 50/50 mix of customer-linked and guest (unlinked) entries.

### CLI

```
bin/magento db:seed --generate=newsletter_subscriber:100
```

### Behavior

- If any customers are in the registry, ~half the subscribers reuse their emails + `customer_id`; the other half get guest Faker emails with `customer_id=0`.
- Each customer is linked at most once per run — dedup is derived from the subscribers already in the registry so state never leaks between runs.
- Status is always `Subscriber::STATUS_SUBSCRIBED`. Store id 1. `status_changed_at` set to current timestamp.

### Cleanup

`--fresh` scopes cleanup to `subscriber_email LIKE '%@example.%'`. If your install has real users with `@example.*` addresses, they will also match — inline comments in the handler flag this. Prefix your real users off `@example.*` if that's a concern.

## Performance

Iterations are batched into database transactions of 50 entries each. For large runs (e.g. `--generate=product:5000`), this cuts per-iteration commit overhead roughly in half. A failing iteration rolls back only its batch's pending work and continues with the next batch.

## License

MIT
