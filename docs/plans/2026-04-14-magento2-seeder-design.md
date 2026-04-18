# Magento 2 Database Seeder — Design Document

## Overview

A Composer-installable Magento 2 module (`RunAsRoot\Seeder`) that brings Laravel-style database seeding to Magento 2. Developers define simple PHP files in a conventional directory and run them via `bin/magento` CLI to populate their local environment with test data.

## Goals

- Zero-friction seeding for common Magento entities (customers, products, categories, orders, CMS)
- Convention-based discovery — drop a file in `dev/seeders/`, done
- Support both simple array-based seeders and powerful class-based seeders
- Extensible via DI so third-party modules can register their own entity handlers
- `--fresh` flag to wipe and re-seed cleanly

## Module Structure

```
src/
├── registration.php
├── composer.json
├── etc/
│   ├── module.xml
│   └── di.xml
├── Api/
│   ├── SeederInterface.php
│   └── EntityHandlerInterface.php
├── Console/
│   └── Command/
│       └── SeedCommand.php
├── Service/
│   ├── SeederRunner.php
│   ├── SeederDiscovery.php
│   └── ArraySeederAdapter.php
├── EntityHandler/
│   ├── CustomerHandler.php
│   ├── ProductHandler.php
│   ├── CategoryHandler.php
│   ├── OrderHandler.php
│   └── CmsHandler.php
```

- `Service/` — Orchestration logic (runner, discovery, adapter). No persistence concerns.
- `EntityHandler/` — One handler per entity type. Uses Magento service contracts to create/delete entities.
- `Model/` — Reserved for actual DB models if ever needed. Empty initially.

## Seeder Formats

### Array-Based Seeder

A PHP file that returns an associative array with `type` and `data`:

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

### Class-Based Seeder

A PHP class implementing `SeederInterface` with full DI support:

```php
<?php
// dev/seeders/MassOrderSeeder.php
use RunAsRoot\Seeder\Api\SeederInterface;

class MassOrderSeeder implements SeederInterface
{
    public function __construct(
        private readonly \RunAsRoot\Seeder\Api\EntityHandlerInterface $orderHandler
    ) {}

    public function getType(): string { return 'order'; }
    public function getOrder(): int { return 50; }

    public function run(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->orderHandler->create([
                'customer_email' => "customer{$i}@test.com",
                'items' => [['sku' => 'test-product', 'qty' => rand(1, 5)]],
            ]);
        }
    }
}
```

## Interfaces

### SeederInterface

```php
namespace RunAsRoot\Seeder\Api;

interface SeederInterface
{
    public function getType(): string;  // 'customer', 'order', etc.
    public function getOrder(): int;    // execution priority (lower = first)
    public function run(): void;
}
```

### EntityHandlerInterface

```php
namespace RunAsRoot\Seeder\Api;

interface EntityHandlerInterface
{
    public function create(array $data): void;
    public function clean(): void;
    public function getType(): string;
}
```

## CLI Interface

```bash
bin/magento db:seed                          # run all seeders
bin/magento db:seed --only=customer,order     # run specific types
bin/magento db:seed --exclude=cms            # skip specific types
bin/magento db:seed --fresh                  # wipe relevant data first, then seed
bin/magento db:seed --fresh --only=customer  # combine flags
bin/magento db:seed --stop-on-error          # halt on first failure
```

### Output

```
Seeding customers... 2 created
Seeding categories... 5 created
Seeding products... 10 created
Seeding orders... 100 created
Seeding CMS... 3 pages, 2 blocks created

Done. 122 entities seeded.
```

## Entity Handlers

| Handler           | Creates via                              | Clean deletes                                         |
|-------------------|------------------------------------------|-------------------------------------------------------|
| CustomerHandler   | CustomerRepositoryInterface::save()      | All non-admin customers                               |
| ProductHandler    | ProductRepositoryInterface::save()       | All products                                          |
| CategoryHandler   | CategoryRepositoryInterface::save()      | All categories except root + default                  |
| OrderHandler      | Quote-to-order flow                      | All orders, invoices, shipments, credit memos         |
| CmsHandler        | PageRepositoryInterface / BlockRepo      | Only seeder-created pages/blocks (prefix-identified)  |

### Notes

- **Orders** go through the full quote → order flow to get proper totals, inventory deductions, etc.
- **CMS clean** is conservative — only deletes what the seeder created (identified by a configurable prefix like `seed-`).
- **Handlers are extensible** via `di.xml` type pool — third parties can register custom handlers.

### DI Configuration

```xml
<type name="RunAsRoot\Seeder\Service\SeederRunner">
    <arguments>
        <argument name="entityHandlers" xsi:type="array">
            <item name="customer" xsi:type="object">RunAsRoot\Seeder\EntityHandler\CustomerHandler</item>
            <item name="product" xsi:type="object">RunAsRoot\Seeder\EntityHandler\ProductHandler</item>
            <item name="category" xsi:type="object">RunAsRoot\Seeder\EntityHandler\CategoryHandler</item>
            <item name="order" xsi:type="object">RunAsRoot\Seeder\EntityHandler\OrderHandler</item>
            <item name="cms" xsi:type="object">RunAsRoot\Seeder\EntityHandler\CmsHandler</item>
        </argument>
    </arguments>
</type>
```

## Seeder Discovery & Execution Flow

### Discovery

1. Scan `{magento_root}/dev/seeders/` for `*Seeder.php` files
2. Include each file:
   - If it returns an array → wrap in `ArraySeederAdapter`
   - If it defines a class implementing `SeederInterface` → instantiate via ObjectManager (DI works)
3. Collect all seeders, sort by order/priority

### Default Seeding Order (respects dependencies)

1. Categories (products need them)
2. Products (orders need them)
3. Customers (orders need them)
4. Orders (depends on products + customers)
5. CMS (independent, goes last)

### Execution Flow

```
bin/magento db:seed --fresh --only=customer,product
        │
        ▼
   SeedCommand (parse flags)
        │
        ▼
   SeederDiscovery (scan dev/seeders/, filter by --only/--exclude)
        │
        ▼
   SeederRunner
        ├── --fresh? → call clean() on matched handlers (reverse order)
        └── run seeders in order
              ├── ArraySeederAdapter → EntityHandler::create()
              └── Class-based → SeederInterface::run()
        │
        ▼
   Output summary
```

### Error Handling

- If a seeder fails: log the error, print it, continue to next seeder
- `--stop-on-error` flag halts on first failure
- No silent exception swallowing

## Design Decisions

1. **Convention over configuration** — `dev/seeders/` directory, `*Seeder.php` naming, no config files needed
2. **Entity handlers abstract Magento ceremony** — users define data, handlers deal with repositories/models
3. **Additive by default, `--fresh` for clean slate** — keeps seeders simple, no idempotency logic in each seeder
4. **DI pool pattern for handlers** — extensible without modifying module code
5. **Service contracts over direct DB** — data integrity, proper events, index-safe
