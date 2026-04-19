# Product Type Support Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add configurable, bundle, grouped, and downloadable product types to the `db:seed --generate` pipeline alongside the existing simple type.

**Architecture:** Keep one `ProductDataGenerator` and one `ProductHandler`. Dispatch subtype-specific work to a new `ProductTypeBuilderPool` with five strategies (one per type). CLI extends with dotted subtypes (`product.configurable:N`) alongside a weighted-split plain `product:N`. Subtype hint flows from the CLI through `GenerateRunConfig` into the data generator.

**Tech Stack:** PHP 8.3, PHPUnit 10, Magento 2.4.8 (Mage-OS), Faker, Warden for integration tests.

**Reference design:** `docs/plans/2026-04-18-product-types-design.md`

**Integration test env:** `/Users/david/Herd/mage-os-typesense` (Warden). Use `cp -R src /Users/david/Herd/mage-os-typesense/app/code/RunAsRoot/Seeder` (copy, not symlink) then `warden env exec php-fpm bash -c "bin/magento <cmd>"`.

---

## Task 1: Add Magento composer dependencies

**Files:**
- Modify: `composer.json`

**Step 1: Add the four new modules to require**

Open `composer.json` and extend the `require` block:

```json
"magento/module-configurable-product": "*",
"magento/module-bundle": "*",
"magento/module-grouped-product": "*",
"magento/module-downloadable": "*",
```

**Step 2: Verify composer.json is still valid JSON**

Run: `composer validate --no-check-all --strict`
Expected: `./composer.json is valid`

**Step 3: Commit**

```bash
git add composer.json
git commit -m "build: require configurable, bundle, grouped, downloadable modules"
```

---

## Task 2: Scaffold TypeBuilderInterface and Pool

**Files:**
- Create: `src/EntityHandler/Product/TypeBuilderInterface.php`
- Create: `src/EntityHandler/Product/TypeBuilderPool.php`
- Create: `tests/Unit/EntityHandler/Product/TypeBuilderPoolTest.php`

**Step 1: Write the failing pool test**

`tests/Unit/EntityHandler/Product/TypeBuilderPoolTest.php`:

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler\Product;

use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilderInterface;
use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilderPool;
use PHPUnit\Framework\TestCase;

final class TypeBuilderPoolTest extends TestCase
{
    public function test_get_returns_registered_builder(): void
    {
        $builder = $this->createMock(TypeBuilderInterface::class);
        $pool = new TypeBuilderPool(['simple' => $builder]);

        $this->assertSame($builder, $pool->get('simple'));
    }

    public function test_has_returns_true_for_registered(): void
    {
        $pool = new TypeBuilderPool(['simple' => $this->createMock(TypeBuilderInterface::class)]);

        $this->assertTrue($pool->has('simple'));
        $this->assertFalse($pool->has('bundle'));
    }

    public function test_get_throws_on_unknown_type(): void
    {
        $pool = new TypeBuilderPool([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No type builder registered for: bundle');

        $pool->get('bundle');
    }

    public function test_constructor_rejects_non_builder(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TypeBuilderPool(['simple' => new \stdClass()]);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/EntityHandler/Product/TypeBuilderPoolTest.php`
Expected: 4 errors (class does not exist).

**Step 3: Create the interface**

`src/EntityHandler/Product/TypeBuilderInterface.php`:

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler\Product;

use Magento\Catalog\Api\Data\ProductInterface;

interface TypeBuilderInterface
{
    /**
     * Apply type-specific data to the product before it is saved.
     */
    public function build(ProductInterface $product, array $data): void;

    /**
     * Hook to run after the product has been saved (e.g. super-links, bundle options).
     */
    public function afterSave(ProductInterface $product, array $data): void;

    public function getType(): string;
}
```

**Step 4: Create the pool**

`src/EntityHandler/Product/TypeBuilderPool.php`:

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler\Product;

class TypeBuilderPool
{
    /** @var array<string, TypeBuilderInterface> */
    private array $builders;

    /** @param array<string, TypeBuilderInterface> $builders */
    public function __construct(array $builders = [])
    {
        foreach ($builders as $key => $builder) {
            if (!$builder instanceof TypeBuilderInterface) {
                throw new \InvalidArgumentException(
                    sprintf('Builder for "%s" must implement %s', $key, TypeBuilderInterface::class)
                );
            }
        }
        $this->builders = $builders;
    }

    public function has(string $type): bool
    {
        return isset($this->builders[$type]);
    }

    public function get(string $type): TypeBuilderInterface
    {
        if (!isset($this->builders[$type])) {
            throw new \RuntimeException(sprintf('No type builder registered for: %s', $type));
        }

        return $this->builders[$type];
    }

    /** @return string[] */
    public function getTypes(): array
    {
        return array_keys($this->builders);
    }
}
```

**Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/EntityHandler/Product/TypeBuilderPoolTest.php`
Expected: 4 passing.

**Step 6: Commit**

```bash
git add src/EntityHandler/Product/TypeBuilderInterface.php src/EntityHandler/Product/TypeBuilderPool.php tests/Unit/EntityHandler/Product/TypeBuilderPoolTest.php
git commit -m "feat: add TypeBuilderInterface and TypeBuilderPool"
```

---

## Task 3: Extract SimpleBuilder from ProductHandler

**Files:**
- Create: `src/EntityHandler/Product/TypeBuilder/SimpleBuilder.php`
- Create: `tests/Unit/EntityHandler/Product/TypeBuilder/SimpleBuilderTest.php`

**Step 1: Write the failing test**

`tests/Unit/EntityHandler/Product/TypeBuilder/SimpleBuilderTest.php`:

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler\Product\TypeBuilder;

use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilder\SimpleBuilder;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Type;
use PHPUnit\Framework\TestCase;

final class SimpleBuilderTest extends TestCase
{
    public function test_get_type_returns_simple(): void
    {
        $this->assertSame(Type::TYPE_SIMPLE, (new SimpleBuilder())->getType());
    }

    public function test_build_sets_type_id_simple_on_product(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->expects($this->once())
            ->method('setTypeId')
            ->with(Type::TYPE_SIMPLE)
            ->willReturnSelf();

        (new SimpleBuilder())->build($product, []);
    }

    public function test_after_save_is_noop(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->expects($this->never())->method($this->anything());

        (new SimpleBuilder())->afterSave($product, []);
    }
}
```

**Step 2: Run test, expect failure**

Run: `vendor/bin/phpunit tests/Unit/EntityHandler/Product/TypeBuilder/SimpleBuilderTest.php`
Expected: class not found.

**Step 3: Implement SimpleBuilder**

`src/EntityHandler/Product/TypeBuilder/SimpleBuilder.php`:

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler\Product\TypeBuilder;

use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Type;

class SimpleBuilder implements TypeBuilderInterface
{
    public function getType(): string
    {
        return Type::TYPE_SIMPLE;
    }

    public function build(ProductInterface $product, array $data): void
    {
        $product->setTypeId(Type::TYPE_SIMPLE);
    }

    public function afterSave(ProductInterface $product, array $data): void
    {
        // No-op; simple products need no post-save work.
    }
}
```

**Step 4: Run test, expect pass**

Run: `vendor/bin/phpunit tests/Unit/EntityHandler/Product/TypeBuilder/SimpleBuilderTest.php`
Expected: 3 passing.

**Step 5: Commit**

```bash
git add src/EntityHandler/Product/TypeBuilder/SimpleBuilder.php tests/Unit/EntityHandler/Product/TypeBuilder/SimpleBuilderTest.php
git commit -m "feat: add SimpleBuilder type strategy"
```

---

## Task 4: Refactor ProductHandler to delegate subtype work to pool

**Files:**
- Modify: `src/EntityHandler/ProductHandler.php`
- Modify: `tests/Unit/EntityHandler/ProductHandlerTest.php`

**Step 1: Add regression test asserting builder is invoked**

In `tests/Unit/EntityHandler/ProductHandlerTest.php`, import `TypeBuilderInterface` and `TypeBuilderPool`:

```php
use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilderInterface;
use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilderPool;
```

Add test method:

```php
public function test_create_delegates_subtype_work_to_builder(): void
{
    $product = $this->createMock(Product::class);
    $product->method('setSku')->willReturnSelf();
    $product->method('setName')->willReturnSelf();
    $product->method('setPrice')->willReturnSelf();
    $product->method('setAttributeSetId')->willReturnSelf();
    $product->method('setStatus')->willReturnSelf();
    $product->method('setVisibility')->willReturnSelf();
    $product->method('setWeight')->willReturnSelf();

    $builder = $this->createMock(TypeBuilderInterface::class);
    $builder->expects($this->once())->method('build')->with($product, $this->arrayHasKey('sku'));
    $builder->expects($this->once())->method('afterSave')->with($product, $this->arrayHasKey('sku'));

    $pool = new TypeBuilderPool(['configurable' => $builder]);

    $factory = $this->createMock(ProductInterfaceFactory::class);
    $factory->method('create')->willReturn($product);

    $handler = $this->createHandler(productFactory: $factory, typeBuilderPool: $pool);

    $handler->create(['sku' => 'CFG-001', 'name' => 'X', 'price' => 10.0, 'product_type' => 'configurable']);
}
```

Update `createHandler()` signature to accept `?TypeBuilderPool $typeBuilderPool = null`, default to a pool with a simple-builder stub that records calls.

**Step 2: Run test, expect failure (constructor signature mismatch)**

Run: `vendor/bin/phpunit tests/Unit/EntityHandler/ProductHandlerTest.php`

**Step 3: Refactor ProductHandler**

Inject `TypeBuilderPool` as a new constructor parameter. Replace the hardcoded `$product->setTypeId(Type::TYPE_SIMPLE)` with:

```php
$subtype = $data['product_type'] ?? Type::TYPE_SIMPLE;
if (!$this->typeBuilderPool->has($subtype)) {
    throw new \RuntimeException(sprintf('Unsupported product_type: %s', $subtype));
}
$builder = $this->typeBuilderPool->get($subtype);
$builder->build($product, $data);
```

Move the existing `setTypeId` call into SimpleBuilder (already done in Task 3). Keep all the shared work (setSku, setName, setPrice, setAttributeSetId, setStatus, setVisibility, setWeight, custom attrs, stock, image, website, repository save, reindex) in the handler. After the save call, invoke `$builder->afterSave($product, $data)`.

Remove the `Type::TYPE_SIMPLE` usage from `ProductHandler::create()`.

**Step 4: Update `createHandler()` helper and constructor bootstrap**

Add to bootstrap.php a stub so the mocked pool Just Works.

**Step 5: Run full suite**

Run: `vendor/bin/phpunit`
Expected: all tests pass, new test included.

**Step 6: Commit**

```bash
git add src/EntityHandler/ProductHandler.php tests/Unit/EntityHandler/ProductHandlerTest.php tests/bootstrap.php
git commit -m "refactor: delegate product-type-specific work to TypeBuilderPool"
```

---

## Task 5: Emit product_type from ProductDataGenerator

**Files:**
- Modify: `src/DataGenerator/ProductDataGenerator.php`
- Modify: `tests/Unit/DataGenerator/ProductDataGeneratorTest.php`

**Step 1: Add failing test**

```php
public function test_generate_includes_product_type_simple_by_default(): void
{
    $faker = \Faker\Factory::create('en_US');
    $registry = new GeneratedDataRegistry();
    $generator = new ProductDataGenerator();

    $data = $generator->generate($faker, $registry);

    $this->assertArrayHasKey('product_type', $data);
    $this->assertSame('simple', $data['product_type']);
}
```

**Step 2: Run → fail**

**Step 3: Add `'product_type' => 'simple'` to the returned array in ProductDataGenerator**

**Step 4: Run → pass**

**Step 5: Commit**

```bash
git add src/DataGenerator/ProductDataGenerator.php tests/Unit/DataGenerator/ProductDataGeneratorTest.php
git commit -m "feat: emit product_type=simple from data generator"
```

---

## Task 6: Implement ConfigurableBuilder

**Files:**
- Create: `src/EntityHandler/Product/TypeBuilder/ConfigurableBuilder.php`
- Create: `tests/Unit/EntityHandler/Product/TypeBuilder/ConfigurableBuilderTest.php`
- Modify: `tests/bootstrap.php` (add stubs for `EavConfig`, `Configurable` resource model, `AttributeInterface`)

**Step 1: Write the failing tests**

Cover:
- `getType()` returns `'configurable'`
- `build()` sets typeId to `configurable`, sets `extension_attributes` configurable_product_options (the configurable attributes)
- `afterSave()` creates N simple children via ProductRepository + sets super-link via Configurable resource model
- If `color` attribute is missing → throws `RuntimeException` with a clear message, does not attempt save
- Child SKUs are derived from parent SKU and attribute option values (deterministic)

Use mocks for: `ProductInterfaceFactory`, `ProductRepositoryInterface`, `EavConfig`, `Configurable` resource model, `AttributeInterface`, `OptionInterface`.

**Step 2: Run → fail**

**Step 3: Implement ConfigurableBuilder**

Dependencies to inject:
- `Magento\Catalog\Api\Data\ProductInterfaceFactory`
- `Magento\Catalog\Api\ProductRepositoryInterface`
- `Magento\Eav\Model\Config` (EavConfig)
- `Magento\ConfigurableProduct\Model\Product\Type\Configurable` as a resource bridge (used for `setUsedProducts`)
- Psr Logger for "skipped: missing color attribute" warnings

Approach in `build()`:
- Set typeId = `configurable`
- Look up `color` and `size` via `EavConfig::getAttribute('catalog_product', ...)`; if either missing or has no options, throw
- Pick 3 color options + 2 size options via faker
- Stash on the product: `setData('_cache_instance_used_product_ids', null)` plus the chosen attribute IDs for afterSave

Approach in `afterSave()`:
- Build 6 simple child products (one per color×size combo):
  - sku = parent-sku + '-' + color-label + '-' + size-label (slugified)
  - name = parent name + ' — ' + color + ' / ' + size
  - price = parent price
  - visibility = NOT_VISIBLE_INDIVIDUALLY (1)
  - status = enabled
  - set color + size option values
  - stock: in_stock, qty=100
  - save via ProductRepository
- Collect child IDs, call `Configurable::setUsedProducts($parent, $childIds)` or set `extension_attributes->setConfigurableProductLinks($childIds)` then `setConfigurableProductOptions($options)`, save parent again

Be prepared to iterate on the exact Magento API surface; the test mocks assert the high-level calls so refactor freely inside `afterSave`.

**Step 4: Run → pass**

**Step 5: Commit**

```bash
git add src/EntityHandler/Product/TypeBuilder/ConfigurableBuilder.php tests/Unit/EntityHandler/Product/TypeBuilder/ConfigurableBuilderTest.php tests/bootstrap.php
git commit -m "feat: add ConfigurableBuilder with 3x2 color/size variants"
```

---

## Task 7: Implement BundleBuilder

**Files:**
- Create: `src/EntityHandler/Product/TypeBuilder/BundleBuilder.php`
- Create: `tests/Unit/EntityHandler/Product/TypeBuilder/BundleBuilderTest.php`
- Modify: `tests/bootstrap.php` (add `OptionInterface`, `LinkInterface`, related factories)

**Step 1: Write the failing tests**

Cover:
- `getType()` returns `'bundle'`
- `build()` sets typeId = bundle, sets price_type = dynamic, sku_type/weight_type dynamic, shipment_type separate
- `afterSave()` creates 3 options with titles "Option N" and random type ∈ {select, radio, checkbox}, each linking 2-3 existing simple SKUs
- If registry has < 6 simples, first falls back to loading `SEED-%` from the DB via `ProductRepository->getList`, else creates N inline hidden simples

**Step 2: Run → fail**

**Step 3: Implement BundleBuilder**

Dependencies:
- `\Magento\Bundle\Api\Data\OptionInterfaceFactory`
- `\Magento\Bundle\Api\Data\LinkInterfaceFactory`
- `\Magento\Bundle\Api\ProductOptionRepositoryInterface`
- `\Magento\Bundle\Api\ProductLinkManagementInterface`
- `\Magento\Catalog\Api\ProductRepositoryInterface`
- `\Magento\Framework\Api\SearchCriteriaBuilder`
- `\RunAsRoot\Seeder\Service\GeneratedDataRegistry`

Build-time sets the dynamic-pricing / dynamic-weight flags via `$product->setData(...)`. afterSave handles option creation:

```php
foreach ($options as $optData) {
    $option = $this->optionFactory->create();
    $option->setTitle($optData['title'])
        ->setType($optData['type'])
        ->setRequired($optData['required'])
        ->setSku($parent->getSku());
    $this->optionRepository->save($parent, $option);

    foreach ($optData['link_skus'] as $linkSku) {
        $link = $this->linkFactory->create();
        $link->setSku($linkSku)->setQty(1)->setPriceType(1)->setIsDefault(false);
        $this->linkManagement->addChild($parent, $option->getOptionId(), $link);
    }
}
```

**Step 4: Run → pass**

**Step 5: Commit**

```bash
git add src/EntityHandler/Product/TypeBuilder/BundleBuilder.php tests/Unit/EntityHandler/Product/TypeBuilder/BundleBuilderTest.php tests/bootstrap.php
git commit -m "feat: add BundleBuilder with 3 options of existing simples"
```

---

## Task 8: Implement GroupedBuilder

**Files:**
- Create: `src/EntityHandler/Product/TypeBuilder/GroupedBuilder.php`
- Create: `tests/Unit/EntityHandler/Product/TypeBuilder/GroupedBuilderTest.php`

**Step 1: Failing tests**

Cover:
- `getType()` returns `'grouped'`
- `build()` sets typeId = grouped
- `afterSave()` links N existing simples via `ProductLinkInterface` with link_type = 'associated'
- Falls back the same way BundleBuilder does (registry → DB → create)

**Step 2: Run → fail**

**Step 3: Implement**

Use `\Magento\Catalog\Api\Data\ProductLinkInterfaceFactory` + `\Magento\Catalog\Api\ProductLinkRepositoryInterface` or `$parent->setProductLinks([...])` then `ProductRepository->save($parent)`.

**Step 4: Run → pass**

**Step 5: Commit**

```bash
git add src/EntityHandler/Product/TypeBuilder/GroupedBuilder.php tests/Unit/EntityHandler/Product/TypeBuilder/GroupedBuilderTest.php
git commit -m "feat: add GroupedBuilder linking 5 existing simples"
```

---

## Task 9: Implement DownloadableBuilder

**Files:**
- Create: `src/EntityHandler/Product/TypeBuilder/DownloadableBuilder.php`
- Create: `tests/Unit/EntityHandler/Product/TypeBuilder/DownloadableBuilderTest.php`
- Modify: `tests/bootstrap.php` (add `LinkInterface`, `LinkInterfaceFactory`, `LinkRepositoryInterface` stubs from `Magento\Downloadable\Api`)

**Step 1: Failing tests**

Cover:
- `getType()` returns `'downloadable'`
- `build()` sets typeId = downloadable, `links_purchased_separately` = 1, `is_virtual` = 0
- `afterSave()` writes one `.txt` file under `pub/media/downloadable/files/seed-<id>.txt` and one sample file, then calls `LinkRepository->save($sku, $link)` with `link_type=file`
- File content is non-empty

**Step 2: Run → fail**

**Step 3: Implement**

Dependencies:
- `\Magento\Downloadable\Api\Data\LinkInterfaceFactory`
- `\Magento\Downloadable\Api\LinkRepositoryInterface`
- `\Magento\Framework\App\Filesystem\DirectoryList`

In afterSave:
```php
$mediaRoot = $this->directoryList->getPath(DirectoryList::MEDIA);
$filesDir = $mediaRoot . '/downloadable/files';
$samplesDir = $mediaRoot . '/downloadable/files_sample';
if (!is_dir($filesDir)) { mkdir($filesDir, 0775, true); }
if (!is_dir($samplesDir)) { mkdir($samplesDir, 0775, true); }

foreach ($data['downloadable']['links'] as $linkData) {
    $id = bin2hex(random_bytes(6));
    $filePath = $filesDir . "/seed-{$id}.txt";
    $samplePath = $samplesDir . "/seed-{$id}-sample.txt";
    file_put_contents($filePath, $linkData['sample_text']);
    file_put_contents($samplePath, substr($linkData['sample_text'], 0, 100));

    $link = $this->linkFactory->create();
    $link->setTitle($linkData['title'])
        ->setPrice(0.0)
        ->setIsShareable(0)
        ->setNumberOfDownloads(0)
        ->setLinkType('file')
        ->setLinkFile(['file' => "/seed-{$id}.txt", 'name' => "seed-{$id}.txt", 'size' => filesize($filePath)])
        ->setSampleType('file')
        ->setSampleFile(['file' => "/seed-{$id}-sample.txt", 'name' => "seed-{$id}-sample.txt"]);
    $this->linkRepository->save($product->getSku(), $link);
}
```

**Step 4: Run → pass**

**Step 5: Commit**

```bash
git add src/EntityHandler/Product/TypeBuilder/DownloadableBuilder.php tests/Unit/EntityHandler/Product/TypeBuilder/DownloadableBuilderTest.php tests/bootstrap.php
git commit -m "feat: add DownloadableBuilder with Faker-generated sample files"
```

---

## Task 10: Wire builders into di.xml

**Files:**
- Modify: `src/etc/di.xml`

**Step 1: Register the pool with five builders**

Add:

```xml
<type name="RunAsRoot\Seeder\EntityHandler\Product\TypeBuilderPool">
    <arguments>
        <argument name="builders" xsi:type="array">
            <item name="simple" xsi:type="object">RunAsRoot\Seeder\EntityHandler\Product\TypeBuilder\SimpleBuilder</item>
            <item name="configurable" xsi:type="object">RunAsRoot\Seeder\EntityHandler\Product\TypeBuilder\ConfigurableBuilder</item>
            <item name="bundle" xsi:type="object">RunAsRoot\Seeder\EntityHandler\Product\TypeBuilder\BundleBuilder</item>
            <item name="grouped" xsi:type="object">RunAsRoot\Seeder\EntityHandler\Product\TypeBuilder\GroupedBuilder</item>
            <item name="downloadable" xsi:type="object">RunAsRoot\Seeder\EntityHandler\Product\TypeBuilder\DownloadableBuilder</item>
        </argument>
    </arguments>
</type>
```

**Step 2: Run unit suite**

Run: `vendor/bin/phpunit`
Expected: all green.

**Step 3: Commit**

```bash
git add src/etc/di.xml
git commit -m "feat: wire ProductTypeBuilderPool with 5 builders in di.xml"
```

---

## Task 11: Extend SeedCommand parser for dotted subtypes

**Files:**
- Modify: `src/Console/Command/SeedCommand.php`
- Modify: `tests/Unit/Console/Command/SeedCommandTest.php`

**Step 1: Failing tests**

```php
public function test_parse_generate_counts_handles_dotted_subtype(): void
{
    // Use reflection or extract parseGenerateCounts into a public static testable method.
    // Assert: 'product:100,product.bundle:20,customer:5' parses to
    //   ['product' => 100, 'product.bundle' => 20, 'customer' => 5]
}

public function test_parse_generate_counts_trims_whitespace(): void
{
    // 'product : 50 ,  product.configurable : 10 ' → ['product' => 50, 'product.configurable' => 10]
}
```

If `parseGenerateCounts` is private, extract into `src/Service/GenerateCountsParser.php` (with its own unit test).

**Step 2: Run → fail**

**Step 3: Implement: allow `product.<subtype>` keys**

The current parser splits on `:` once; subtypes don't need any grammar change, the dot is just part of the key. Verify and add a validator that rejects anything other than known product subtypes or bare entity types.

**Step 4: Run → pass**

**Step 5: Commit**

```bash
git add src/Console/Command/SeedCommand.php tests/Unit/Console/Command/SeedCommandTest.php
git commit -m "feat: SeedCommand parser accepts product.<subtype>:N"
```

---

## Task 12: Route subtype hint + weighted split into the generator

**Files:**
- Modify: `src/Service/GenerateRunConfig.php`
- Modify: `src/Service/GenerateRunner.php`
- Modify: `src/Service/DependencyResolver.php`
- Modify: `src/DataGenerator/ProductDataGenerator.php`
- Modify: relevant unit tests

**Step 1: Failing integration-style unit test in `GenerateRunnerTest`**

Assert: passing `counts = ['product' => 10]` to the runner results in ProductDataGenerator receiving 10 calls where `product_type` across calls includes at least two different values (sampled from weighted set). Assert: passing `counts = ['product.bundle' => 5]` results in 5 calls all returning `product_type = 'bundle'`.

**Step 2: Run → fail**

**Step 3: Extend pipeline**

- `DependencyResolver`: when normalizing counts, strip subtype dots for dependency math (so `product.bundle:20` consumes the same category deps as `product:20`), but keep the dotted keys in the output map.
- `GenerateRunner::generateType`: if key contains `.`, split into `(baseType, subtype)`; look up generator by `baseType`; before the iteration loop, if the generator implements a new `SubtypeAwareInterface`, call `setForcedSubtype($subtype)`; reset to null in a `finally`.
- `ProductDataGenerator` implements `SubtypeAwareInterface`:
  - Holds a `?string $forcedSubtype`
  - `generate()` picks subtype: forced value if set, else weighted random via a constant `const WEIGHTS = ['simple' => 70, 'configurable' => 10, 'bundle' => 10, 'grouped' => 5, 'downloadable' => 5]`
  - Populates subtype-specific payload based on the picked value (structure per design doc)

**Step 4: Run → pass**

**Step 5: Commit**

```bash
git add src/ tests/
git commit -m "feat: runner routes dotted product subtypes; generator weighted-splits plain product:N"
```

---

## Task 13: Update documentation

**Files:**
- Modify: `README.md`

**Step 1: Add a "Product Types" section**

Document:
- The five supported subtypes and how each is built.
- The CLI syntax (plain `product:N`, dotted `product.<subtype>:N`, combined usage).
- The default split weights.
- The color/size attribute requirement for configurable (and the graceful skip).
- Downloadable file location under `pub/media/downloadable/`.

**Step 2: Commit**

```bash
git add README.md
git commit -m "docs: document product type support in --generate"
```

---

## Task 14: Integration smoke test against mage-os-typesense

**Prerequisite:** `/Users/david/Herd/mage-os-typesense` is up via Warden.

**Step 1: Deploy module**

```bash
rm -rf /Users/david/Herd/mage-os-typesense/app/code/RunAsRoot/Seeder
mkdir -p /Users/david/Herd/mage-os-typesense/app/code/RunAsRoot
cp -R /Users/david/Herd/seeder/src /Users/david/Herd/mage-os-typesense/app/code/RunAsRoot/Seeder
cd /Users/david/Herd/mage-os-typesense
warden env exec php-fpm composer require fakerphp/faker:"^1.23" --no-interaction
warden env exec php-fpm bin/magento module:enable RunAsRoot_Seeder
warden env exec php-fpm bash -c "rm -rf generated/code/RunAsRoot generated/metadata/* var/cache/*"
warden env exec php-fpm bin/magento cache:flush
warden env exec php-fpm bin/magento setup:upgrade
```

**Step 2: Seed one of each subtype**

```bash
warden env exec php-fpm bash -c "bin/magento db:seed --generate=product.simple:3,product.configurable:2,product.bundle:2,product.grouped:2,product.downloadable:2 --seed=20260418"
```

Expected CLI output: `Done. N entities generated.` with zero failures.

**Step 3: Verify DB rows**

```bash
warden env exec -T db mysql -uroot -pmagento -N -e "USE magento;
SELECT type_id, COUNT(*) FROM catalog_product_entity WHERE sku LIKE 'SEED-%' GROUP BY type_id;
SELECT COUNT(*) AS super_links FROM catalog_product_super_link;
SELECT COUNT(*) AS bundle_opts FROM catalog_product_bundle_option;
SELECT COUNT(*) AS grouped_links FROM catalog_product_link WHERE link_type_id = 3;
SELECT COUNT(*) AS downloadable_links FROM downloadable_link;"
```

Expected: all five type_ids present; `super_links > 0`; `bundle_opts > 0`; `grouped_links > 0`; `downloadable_links > 0`.

**Step 4: Verify frontend rendering**

Visit (via browser or curl through Warden):
- One configurable product URL → swatches render, add-to-cart works
- One bundle product URL → options visible
- One grouped product URL → associated-products table renders
- One downloadable product URL → link appears

**Step 5: Place an order covering each type**

```bash
warden env exec php-fpm bash -c "bin/magento db:seed --generate=order:3 --seed=20260419"
```

Expected: orders placed using products that may include non-simples.

**Step 6: Cleanup test install**

```bash
rm -rf /Users/david/Herd/mage-os-typesense/app/code/RunAsRoot
warden env exec php-fpm bash -c "sed -i \"/'RunAsRoot_Seeder'/d\" app/etc/config.php"
warden env exec php-fpm bin/magento cache:flush
```

**Step 7: Final commit**

No code changes — just tag verification done:

```bash
git log --oneline | head -5
```

---

## Task 15: Scaffold StateTransitionInterface and Pool (orders)

**Files:**
- Create: `src/EntityHandler/Order/StateTransitionInterface.php`
- Create: `src/EntityHandler/Order/StateTransitionPool.php`
- Create: `tests/Unit/EntityHandler/Order/StateTransitionPoolTest.php`

Mirror the `TypeBuilderPool` pattern exactly: constructor takes a keyed array of transitions, validates each implements the interface, exposes `get`/`has`/`getTypes`. Interface:

```php
interface StateTransitionInterface
{
    public function getState(): string;
    public function apply(\Magento\Sales\Api\Data\OrderInterface $order, array $data): void;
}
```

TDD same as Task 2.

**Commit:** `feat: add Order StateTransitionInterface and pool`

---

## Task 16: Implement NewTransition, HoldedTransition, CanceledTransition

**Files:**
- Create: `src/EntityHandler/Order/StateTransition/NewTransition.php`
- Create: `src/EntityHandler/Order/StateTransition/HoldedTransition.php`
- Create: `src/EntityHandler/Order/StateTransition/CanceledTransition.php`
- Create: three matching test files under `tests/Unit/EntityHandler/Order/StateTransition/`

NewTransition is a no-op. HoldedTransition calls `$order->hold()` then saves via `OrderRepositoryInterface`. CanceledTransition calls `$order->cancel()` then saves.

TDD per transition: assert `hold()` / `cancel()` is called, then `save($order)`.

**Commit:** `feat: add new/holded/canceled order state transitions`

---

## Task 17: Implement ProcessingTransition (invoice)

**Files:**
- Create: `src/EntityHandler/Order/StateTransition/ProcessingTransition.php`
- Create: `tests/Unit/EntityHandler/Order/StateTransition/ProcessingTransitionTest.php`
- Modify: `tests/bootstrap.php` (add stubs for `InvoiceService`, `InvoiceRepositoryInterface`, `InvoiceInterface`, `TransactionFactory`)

Injects `\Magento\Sales\Model\Service\InvoiceService` + `\Magento\Sales\Api\InvoiceRepositoryInterface` + `\Magento\Framework\DB\TransactionFactory`.

Flow:

```php
$invoice = $this->invoiceService->prepareInvoice($order);
$invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
$invoice->register();
$transaction = $this->transactionFactory->create();
$transaction->addObject($invoice)->addObject($order)->save();
```

Test asserts the invoice is prepared, registered, and committed via transaction.

**Commit:** `feat: add processing transition (invoice)`

---

## Task 18: Implement CompleteTransition (invoice + shipment)

**Files:**
- Create: `src/EntityHandler/Order/StateTransition/CompleteTransition.php`
- Create: `tests/Unit/EntityHandler/Order/StateTransition/CompleteTransitionTest.php`
- Modify: `tests/bootstrap.php` (add stubs for `ShipmentFactory`, `ShipmentRepositoryInterface`)

Reuses the processing flow, then adds a shipment:

```php
$shipment = $this->shipmentFactory->create($order, $itemsQty);
$shipment->register();
$transaction = $this->transactionFactory->create();
$transaction->addObject($shipment)->addObject($order)->save();
```

`$itemsQty` maps `order_item_id => qty` for all items.

**Commit:** `feat: add complete transition (invoice + shipment)`

---

## Task 19: Implement ClosedTransition (invoice + credit memo)

**Files:**
- Create: `src/EntityHandler/Order/StateTransition/ClosedTransition.php`
- Create: `tests/Unit/EntityHandler/Order/StateTransition/ClosedTransitionTest.php`
- Modify: `tests/bootstrap.php` (add stubs for `CreditmemoFactory`, `CreditmemoService`)

Flow: invoice (as in ProcessingTransition), then:

```php
$creditmemo = $this->creditmemoFactory->createByOrder($order);
$this->creditmemoService->refund($creditmemo, true);
```

The second arg `true` means offline — no payment gateway call.

**Commit:** `feat: add closed transition (invoice + credit memo)`

---

## Task 20: Emit order_state from OrderDataGenerator

**Files:**
- Modify: `src/DataGenerator/OrderDataGenerator.php`
- Modify: `tests/Unit/DataGenerator/OrderDataGeneratorTest.php`

Same pattern as ProductDataGenerator (Task 5 + 12). Add `order_state` to returned data. Implement `SubtypeAwareInterface`. Default weights:

```php
const STATE_WEIGHTS = [
    'new' => 15,
    'processing' => 25,
    'complete' => 40,
    'canceled' => 10,
    'holded' => 5,
    'closed' => 5,
];
```

Tests assert the key is present, forced subtype is respected, and weighted distribution across many seeded runs produces all states at least once.

**Commit:** `feat: OrderDataGenerator emits weighted order_state`

---

## Task 21: Wire OrderHandler to invoke transition after placeOrder

**Files:**
- Modify: `src/EntityHandler/OrderHandler.php`
- Modify: `tests/Unit/EntityHandler/OrderHandlerTest.php`
- Modify: `src/etc/di.xml` (wire `StateTransitionPool` with 6 transitions)

Add `StateTransitionPool` as a constructor dependency. After `placeOrder($cartId)`:

```php
$order = $this->orderRepository->get($orderId);
$state = $data['order_state'] ?? 'new';
if ($this->transitionPool->has($state)) {
    $this->transitionPool->get($state)->apply($order, $data);
}
```

`placeOrder` returns the order ID as `int`. Load the order fresh so it has the status/state assigned by `placeOrder` before transitioning.

Test asserts the matching transition is invoked with the loaded order.

**Commit:** `feat: OrderHandler routes orders through state transitions`

---

## Task 22: Integration smoke test for order states

Extend the mage-os-typesense smoke run:

```bash
warden env exec php-fpm bash -c "bin/magento db:seed --generate=order:50 --seed=20260420"
```

Expected: CLI reports 50 orders, zero failures.

DB verification:

```bash
warden env exec -T db mysql -uroot -pmagento -N -e "USE magento;
SELECT state, COUNT(*) FROM sales_order WHERE entity_id > (SELECT COALESCE(MAX(entity_id),0)-50 FROM (SELECT entity_id FROM sales_order) t)
GROUP BY state;
SELECT COUNT(*) FROM sales_invoice;
SELECT COUNT(*) FROM sales_shipment;
SELECT COUNT(*) FROM sales_creditmemo;
SELECT COUNT(*) FROM sales_order WHERE state IN ('holded','canceled');"
```

Expected: at least 4 distinct states across the 50 orders, at least one invoice, one shipment, one credit memo. Ratios roughly match weights (complete is majority).

Force-state query to prove explicit subtype works:

```bash
warden env exec php-fpm bash -c "bin/magento db:seed --generate=order.complete:5,order.canceled:2 --seed=20260421"
```

Verify the latest 7 orders include exactly 5 `complete` and 2 `canceled`.

Commit the verification notes nowhere — this task is pure verification.

---

## Completion criteria

- All 104+ unit tests pass (expect ~35 new tests added across product builders, order transitions, runner).
- Integration smoke against mage-os-typesense produces at least one of each product type in the DB, visible on frontend.
- Orders distributed across at least 4 of 6 states in a 50-order run; explicit `order.<state>:N` forces exactly that state.
- `db:seed --generate=product:N` produces a mix across all five types (verified by type distribution query).
- `--stop-on-error` still halts on the first failure.
- `--fresh` still cleans prior SEED- data including bundle options, super links, downloadable links, invoices, shipments, credit memos.
- README documents the new product-type and order-state syntax.
