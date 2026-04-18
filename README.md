# Magento 2 Database Seeder

Laravel-style database seeding for Magento 2. Define simple PHP files, run `bin/magento db:seed`, populate your dev environment.

## Installation

```bash
composer require runasroot/module-seeder --dev
bin/magento module:enable RunAsRoot_Seeder
bin/magento setup:upgrade
```

## Quick Start

1. Create a `dev/seeders/` directory in your Magento root
2. Drop seeder files in it (copy from `examples/` to get started)
3. Run `bin/magento db:seed`

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
```

## Seeder Formats

### Array-Based (simple)

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

### Class-Based (powerful)

For complex scenarios — loops, Faker, conditional logic:

```php
<?php
// dev/seeders/MassOrderSeeder.php
use RunAsRoot\Seeder\Api\SeederInterface;
use RunAsRoot\Seeder\Service\EntityHandlerPool;

class MassOrderSeeder implements SeederInterface
{
    public function __construct(private readonly EntityHandlerPool $handlerPool) {}

    public function getType(): string { return 'order'; }
    public function getOrder(): int { return 40; }

    public function run(): void
    {
        $handler = $this->handlerPool->get('order');
        for ($i = 0; $i < 50; $i++) {
            $handler->create([
                'customer_email' => "customer{$i}@test.com",
                'items' => [['sku' => 'PRODUCT-001', 'qty' => rand(1, 5)]],
            ]);
        }
    }
}
```

## Supported Entity Types

| Type       | Key          | What it creates                     |
|------------|-------------|-------------------------------------|
| `customer` | `customer`  | Customer accounts                   |
| `category` | `category`  | Category tree nodes                 |
| `product`  | `product`   | Products (all five Magento types)   |
| `order`    | `order`     | Orders via quote-to-order flow      |
| `cms`      | `cms`       | CMS pages and blocks                |

## Default Seeding Order

1. Categories (10)
2. Products (20)
3. Customers (30)
4. Orders (40)
5. CMS (50)

Override with `'order' => 5` in array seeders or `getOrder(): int` in class seeders.

## The `--fresh` Flag

When using `--fresh`, the module cleans existing data before seeding:

- **Customers**: Deletes all non-admin customers
- **Products**: Deletes all products
- **Categories**: Deletes all categories except root (ID 1) and default (ID 2)
- **Orders**: Deletes all orders (FK cascades handle invoices, shipments, etc.)
- **CMS**: Only deletes pages/blocks with the `seed-` identifier prefix

Clean runs in reverse dependency order (orders -> products -> categories -> customers -> CMS).

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

### Smart Dependency Resolution

You only need to request the entities you want. Dependencies are auto-generated with sensible ratios.

For example, `--generate=order:1000` will also generate the required customers, products, and categories automatically.

| Requested       | Auto-generates                                                                           |
|-----------------|------------------------------------------------------------------------------------------|
| `order:1000`    | `customer:200` (1:5 ratio), `product:50` (1:20 ratio), `category:10` (1:5 of products)  |
| `product:100`   | `category:20` (1:5 ratio)                                                                |
| `customer:500`  | Nothing (no dependencies)                                                                |
| `category:50`   | Nothing (no dependencies)                                                                |
| `cms:20`        | Nothing (no dependencies)                                                                |

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

## License

MIT
