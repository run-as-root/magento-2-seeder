# Changelog

All notable changes to `runasroot/module-seeder` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.0.0]: https://github.com/run-as-root/magento-2-seeder/releases/tag/v1.0.0
