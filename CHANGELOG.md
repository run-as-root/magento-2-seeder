# Changelog

All notable changes to `runasroot/module-seeder` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2026-04-21

### Added

- New `bin/magento db:seed:make` command — interactive scaffolder for seeder files. Eliminates the empty-`dev/seeders/` dead-end: new users can now go from zero to a working seeder without reading the README. Supports both interactive mode (TTY) and flag-driven mode (`--type`, `--count`, `--format`, `--name`, `--locale`, `--seed`, `--force`) for CI/scripts.
- Multi-select entity types in the interactive flow: pick any combination from the eight supported types in one prompt, get one scaffolded file per selected type. Each type gets its own `count` prompt; locale, seed, and format are shared.
- Cascade hints in the type picker — labels show dependency relationships so users understand what seeding one type implies (e.g. `order — cascades: customer, product, category`). Purely informational; `DependencyResolver` still handles resolution at seed time.
- Progress rendering for count-based file seeders. Running `db:seed` against a scaffolded `count: 100` file used to run silent for minutes; now renders a Laravel Prompts progress bar via the new `ProgressReporter` service. Threshold matches the old `--generate` behavior (`count >= 10`).
- Spinner for custom (non-count) `SeederInterface` implementations in file-based seed runs. TTY-gated so CI pipelines and log files stay clean.
- `SeederFileBuilder` service — pure, stateless, unit-testable without Magento bootstrap. Emits PHP / JSON / YAML shapes that roundtrip through the existing `ArraySeederAdapter` loader. Hardened against quote escaping and code injection in `$type` / `$locale` via `var_export()`.
- `Test/Integration/Console/SeedMakeRoundtripTest` — integration coverage of the full scaffold → seed pipeline against a live Mage-OS install. Uses `@magentoDbIsolation enabled` + delta-based assertions.
- README section documenting the scaffolder with interactive + flag-driven examples and a flag reference table.

### Changed

- `db:seed` on an empty/missing `dev/seeders/` now prints `Run bin/magento db:seed:make to scaffold one.` alongside the existing `No seeders found` message. Exit code unchanged.
- `SeederRunner::run()` now accepts an optional `?callable $onProgress` second argument. Fully BC — existing callers passing only `$config` keep working.
- `ArraySeederAdapter` now exposes `setProgressCallback(?callable)` and `hasCount()` so file-based count seeders can render progress identical to `--generate=type:N`.
- `--generate` progress rendering migrated from Symfony `ProgressBar` to Laravel Prompts `Progress`. Visual-only change, same `$total < 10` threshold.

### Fixed

- Progress cursor restoration — `SeedCommand::executeGenerate()` now wraps `GenerateRunner::run()` in `try { ... } finally { $progressReporter->finish() }` so the terminal cursor is always restored, even if generation throws.
- Scaffolder rejects `--name` values that don't end in `Seeder` (matches the glob `SeederDiscovery` requires; otherwise the scaffolded file would be silently ignored at `db:seed` time).
- Scaffolder's interactive flow no longer re-prompts for `--locale=en_US` / `--format=php` when the user explicitly passed those defaults on the CLI.

### Installation

```bash
composer require runasroot/module-seeder:^1.3
bin/magento setup:upgrade
```

Fully backward compatible with 1.2.x — no breaking public API changes. Adds two composer requires: `laravel/prompts: ^0.3` (runtime, for the scaffolder + progress UI) and `mockery/mockery: ^1.6` (dev-only, for `Prompt::fake()` in tests).

### Contributors

- @DavidLambauer — entire release

## [1.2.1] - 2026-04-20

### Fixed

- `db:seed --fresh` no longer crashes with "Delete operation is forbidden for current area" on installs that carry sample data (or any non-seeder products/categories). `SeedCommand` now registers `isSecureArea = true` alongside the existing `adminhtml` area code, so category/product cleanup can pass Magento's delete guards from CLI. Registration is skipped when the flag is already set by an outer caller to avoid clobbering.

## [1.2.0] - 2026-04-20

### Added

- Three new entity types: `cart_rule`, `wishlist`, `newsletter_subscriber` — each with a `DataGenerator` + `EntityHandler` pair. Reachable via `--generate=cart_rule:N`, `--generate=wishlist:N`, `--generate=newsletter_subscriber:N`, or the fluent `seed('<type>')` builder entry.
- `CartRuleDataGenerator` — weighted action mix of `by_percent` (60%) / `by_fixed` (30%) / `free_shipping` (10%). Each rule gets one attached manual coupon with code format `<PREFIX><amount>-<6 uppercase alnum>`, active for all websites + all four default customer groups, expires in 1 year, applies to all carts (empty condition tree).
- `WishlistDataGenerator` — one wishlist per seeded customer with 1–5 random products. Declares `customer:N` as dependency so `--generate=wishlist:50` auto-seeds 50 customers. Handler inserts into `wishlist_item` directly to sidestep stock-index race conditions on freshly seeded products.
- `NewsletterSubscriberDataGenerator` — 50/50 mix of customer-linked subscribers (email reused from registry) and guest emails. Dedup of linked customers derived from registry state so state never leaks between runs.
- `CustomerDataGenerator` now emits 1–3 addresses per customer (first remains default billing/shipping, extras are non-default).
- `Test/Integration/NewEntityTypesSmokeTest` — integration smoke coverage for all three new types against a real Mage-OS install (rules + coupons created, subscribers split linked/guest, wishlists + items + qty verified).
- 23 new unit tests covering handler branches: `CartRuleHandlerTest` (retry loop on coupon collision, free_shipping branch, cleanup filter), `WishlistHandlerTest` (direct-insert bind columns, store_id fallback, customer-scoped cleanup), `NewsletterSubscriberHandlerTest` (load-or-merge, default coalesce, cleanup).
- README sections documenting Cart Rules, Wishlists, and Newsletter Subscribers (CLI, action/behavior mix, cleanup scope).

### Changed

- `src/etc/di.xml`: all `EntityHandlerPool` and `DataGeneratorPool` items now wire through `\Proxy`. `bin/magento setup:install` was eagerly instantiating EAV-touching handlers (Customer, Product) before the schema existed, crashing with `TableNotFoundException: eav_entity_type`. Proxies defer construction to first use — idiomatic for handler pools behind console commands.

### Fixed

- `ProductHandler` resolves the image import directory via `DirectoryList::getPath(DirectoryList::MEDIA)` instead of hard-coded `getRoot() . '/pub/media/...'`. Sandboxed installs that remap `MEDIA` (split-pub deployments, integration test harness) no longer fail PathValidator checks.

### Installation

```bash
composer require runasroot/module-seeder:^1.2
bin/magento setup:upgrade
```

Fully backward compatible with 1.1.x — no public API changes. Adds three new composer requires (`magento/module-sales-rule`, `magento/module-wishlist`, `magento/module-newsletter`) which every modern Magento 2 / Mage-OS install already ships with.

### Contributors

- @DavidLambauer — entire release

## [1.1.0] - 2026-04-20

### Added

- Abstract `RunAsRoot\Seeder\Seeder` base class so class-based seeders skip the `EntityHandlerPool` boilerplate and extend a typed fluent base.
- Fluent `RunAsRoot\Seeder\SeedBuilder` API: `$this->orders()->count(50)->with([...])->using($fn)->subtype('bundle')->create()`. Per-iteration callbacks receive `(int $i, Faker\Generator $faker)`.
- `examples/FluentOrderSeeder.php` demonstrating the new style.

### Changed

- `SeedBuilder::create()` writes created entity data (including the `id` returned by the handler) into `GeneratedDataRegistry` under the base type, matching `GenerateRunner` semantics. This means later builders within the same `run()` can reference ids through generators.

## [1.0.0] - 2026-04-19

First stable release. Establishes the public API baseline for
`RunAsRoot\Seeder\Api\EntityHandlerInterface` and the `db:seed` /
`db:seed:status` CLI surface.

### Added

#### CLI

- `bin/magento db:seed` for array-based seeder files in `dev/seeders/` — `--only`, `--exclude`, `--fresh`, `--stop-on-error`
- `bin/magento db:seed --generate=type:N[,type2:N2,...]` for Faker-powered mass data generation with smart dependency resolution (requesting `order:1000` auto-generates customers / products / categories at sensible ratios)
- `--locale` and `--seed` flags for deterministic / localized generation
- `bin/magento db:seed:status` — prints DB counts of all seeded entity types (products, categories, customers, orders, CMS pages / blocks, reviews)

#### Entity support

- Five core handlers: `category`, `product`, `customer`, `order`, `cms`
- Five product type builders: `simple`, `configurable` (3×2 color / size variants), `bundle` (3 options, dynamic pricing), `grouped` (links up to 5 simple products), `downloadable` (Faker-generated sample / link files via `addImageToMediaGallery` + `ContentInterface`)
- Dotted subtype syntax `product.configurable:N` alongside weighted split `product:N`
- Order state transitions: `new`, `holded`, `canceled`, `processing` (invoice offline), `complete` (invoice + shipment), `closed` (invoice + offline credit memo)
- Automatic product reviews: each seeded product gets 0–10 Faker-generated reviews (nickname, title, detail, 1–5 star rating) attached as Approved on store 1 so they render immediately on the frontend. Rating application is resilient — a single bad rating no longer skips the rest

#### Infrastructure

- `DependencyResolver` — auto-generates missing dependency types at ratios (`order:1000` → `customer:200`, `product:50`, `category:10`)
- `GeneratedDataRegistry` — carries saved entity ids across generators so downstream types can reference upstream entities (products → categories, orders → customers + products)
- Even category distribution for products via least-used-first algorithm
- `ImageDownloader` — picsum.photos integration, images attached via `addImageToMediaGallery` with `move=true` so they land in `pub/media/catalog/product/` instead of orphaned in `import/`
- `ArraySeederAdapter` — bridges simple array config (`['type' => ..., 'data' => [...]]`) to the generator pipeline
- `ReviewCreator` service wrapping Magento's Review + Rating API with swallowed per-review errors
- `.fresh` protection — root + default categories never get cleaned; CMS uses `seed-` identifier prefix for conservative cleanup; `SEED-%` SKUs scope product + review cleanup
- 202 unit tests with Magento-free bootstrap stubs in `tests/bootstrap.php`

#### Repository

- MIT `LICENSE`
- GitHub Actions CI workflow
- Pull request template

### Changed

- **BREAKING** (#2): `EntityHandlerInterface::create(array $data): void` is now `: int` and returns the saved entity primary key. Consumer projects with custom `EntityHandlerInterface` implementations registered via `di.xml` must update their return type — PHP will surface this at class-load with a clear "declaration must be compatible" error.

### Fixed

- **BREAKING behaviour change** (#2): `GenerateRunner` now writes the handler-returned entity id into `GeneratedDataRegistry` before passing it to downstream generators. Previously every seeded product landed in root "Default Category" (id 2) regardless of seeded categories, because `ProductDataGenerator`'s `$category['id'] ?? 2` always fell through to the fallback. Consumers relying on the bugged behaviour will see products correctly distributed across seeded categories after upgrading.
- `CustomerDataGenerator` sanitizes Faker `phoneNumber()` to match Magento's regex (`0-9 + - ( ) space`). Faker's US format includes `.`, `x`, `ext.` which previously caused 100% customer save failures on some seeds and cascaded into order failures.
- `ProductHandler` sets `setStockData()` + `setWebsiteIds()` before save and forces `cataloginventory_stock` reindex so `Quote::addProduct` accepts products when the indexer is in Schedule mode.
- `OrderHandler` sets the current store via `StoreManagerInterface` and assigns `store_id` to the quote before placing; previously `createEmptyCart()` ran in admin context (`store_id=0`) and `Quote::addProduct` rejected products as "not available".
- `GenerateRunner` tracks a failed count per type and reports `success=false` on any iteration throwing. `SeedCommand` now exits non-zero with the error message. Previously silent: "Generated 0 order(s)... done" exited 0 even when every iteration threw.
- Order state now persists after invoice / shipment / credit-memo transitions — brute-force reload + save after the `InvoiceService` / `ShipmentFactory` pipeline, because the internal state resolver sometimes left orders stuck on `new`.
- `OrderDataGenerator` only picks simple products as order items, and forces a simple-product dependency so orders always have usable items even in mixed-type runs.

### Installation

```bash
composer require runasroot/module-seeder:^1.0
bin/magento module:enable RunAsRoot_Seeder
bin/magento setup:upgrade
bin/magento setup:di:compile
```

### Contributors

- @DavidLambauer — entire release

[1.2.0]: https://github.com/run-as-root/magento-2-seeder/releases/tag/v1.2.0
[1.1.0]: https://github.com/run-as-root/magento-2-seeder/releases/tag/v1.1.0
[1.0.0]: https://github.com/run-as-root/magento-2-seeder/releases/tag/v1.0.0
