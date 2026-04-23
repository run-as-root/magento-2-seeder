<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler;

use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilderInterface;
use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilderPool;
use RunAsRoot\Seeder\EntityHandler\ProductHandler;
use RunAsRoot\Seeder\Service\ImageDownloader;
use RunAsRoot\Seeder\Service\ReviewCreator;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\Data\ProductSearchResultsInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\CatalogInventory\Model\Indexer\Stock\Processor as StockIndexerProcessor;
use Magento\Framework\App\Filesystem\DirectoryList;
use PHPUnit\Framework\TestCase;

final class ProductHandlerTest extends TestCase
{
    public function test_get_type_returns_product(): void
    {
        $handler = $this->createHandler();

        $this->assertSame('product', $handler->getType());
    }

    public function test_create_saves_simple_product(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->expects($this->once())->method('setSku')->with('TEST-001')->willReturnSelf();
        $product->expects($this->once())->method('setName')->with('Test Product')->willReturnSelf();
        $product->expects($this->once())->method('setPrice')->with(29.99)->willReturnSelf();
        $product->expects($this->once())->method('setAttributeSetId')->with(4)->willReturnSelf();
        $product->method('setStatus')->willReturnSelf();
        $product->method('setVisibility')->willReturnSelf();
        $product->method('setTypeId')->willReturnSelf();
        $product->method('setWeight')->willReturnSelf();
        $product->method('setCustomAttribute')->willReturnSelf();

        $factory = $this->createMock(ProductInterfaceFactory::class);
        $factory->method('create')->willReturn($product);

        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects($this->once())->method('save')->with($product)->willReturn($product);

        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->expects($this->once())->method('setQty')->with(100.0)->willReturnSelf();
        $stockItem->expects($this->once())->method('setIsInStock')->with(true)->willReturnSelf();

        $stockRegistry = $this->createMock(StockRegistryInterface::class);
        $stockRegistry->expects($this->once())
            ->method('getStockItemBySku')
            ->with('TEST-001')
            ->willReturn($stockItem);
        $stockRegistry->expects($this->once())
            ->method('updateStockItemBySku')
            ->with('TEST-001', $stockItem);

        $handler = $this->createHandler(
            productFactory: $factory,
            productRepository: $repository,
            stockRegistry: $stockRegistry,
        );

        $handler->create([
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'price' => 29.99,
        ]);
    }

    public function test_create_sets_custom_stock_qty(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('setSku')->willReturnSelf();
        $product->method('setName')->willReturnSelf();
        $product->method('setPrice')->willReturnSelf();
        $product->method('setAttributeSetId')->willReturnSelf();
        $product->method('setStatus')->willReturnSelf();
        $product->method('setVisibility')->willReturnSelf();
        $product->method('setTypeId')->willReturnSelf();
        $product->method('setWeight')->willReturnSelf();

        $factory = $this->createMock(ProductInterfaceFactory::class);
        $factory->method('create')->willReturn($product);

        $repository = $this->createMock(ProductRepositoryInterface::class);

        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->expects($this->once())->method('setQty')->with(50.0)->willReturnSelf();
        $stockItem->expects($this->once())->method('setIsInStock')->with(true)->willReturnSelf();

        $stockRegistry = $this->createMock(StockRegistryInterface::class);
        $stockRegistry->expects($this->once())
            ->method('getStockItemBySku')
            ->with('CUSTOM-QTY')
            ->willReturn($stockItem);
        $stockRegistry->expects($this->once())
            ->method('updateStockItemBySku')
            ->with('CUSTOM-QTY', $stockItem);

        $handler = $this->createHandler(
            productFactory: $factory,
            productRepository: $repository,
            stockRegistry: $stockRegistry,
        );

        $handler->create([
            'sku' => 'CUSTOM-QTY',
            'name' => 'Custom Qty Product',
            'price' => 19.99,
            'qty' => 50,
        ]);
    }

    public function test_create_attaches_downloaded_image_to_media_gallery(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('setSku')->willReturnSelf();
        $product->method('setName')->willReturnSelf();
        $product->method('setPrice')->willReturnSelf();
        $product->method('setAttributeSetId')->willReturnSelf();
        $product->method('setStatus')->willReturnSelf();
        $product->method('setVisibility')->willReturnSelf();
        $product->method('setTypeId')->willReturnSelf();
        $product->method('setWeight')->willReturnSelf();

        $imageDownloader = $this->createMock(ImageDownloader::class);
        $imageDownloader->expects($this->once())
            ->method('download')
            ->with('https://example.com/img.jpg', $this->stringContains('/catalog/product/import'))
            ->willReturn('seed_abc.jpg');

        $directoryList = $this->createMock(DirectoryList::class);
        $directoryList->method('getPath')
            ->with(DirectoryList::MEDIA)
            ->willReturn('/var/www/html/pub/media');

        $product->expects($this->once())
            ->method('addImageToMediaGallery')
            ->with(
                '/var/www/html/pub/media/catalog/product/import/seed_abc.jpg',
                ['image', 'small_image', 'thumbnail'],
                true,
                false
            );

        $factory = $this->createMock(ProductInterfaceFactory::class);
        $factory->method('create')->willReturn($product);

        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects($this->once())->method('save')->with($product)->willReturn($product);

        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('setQty')->willReturnSelf();
        $stockItem->method('setIsInStock')->willReturnSelf();

        $stockRegistry = $this->createMock(StockRegistryInterface::class);
        $stockRegistry->method('getStockItemBySku')->willReturn($stockItem);

        $handler = $this->createHandler(
            productFactory: $factory,
            productRepository: $repository,
            stockRegistry: $stockRegistry,
            imageDownloader: $imageDownloader,
            directoryList: $directoryList,
        );

        $handler->create([
            'sku' => 'TEST-IMG',
            'name' => 'Image Product',
            'price' => 10.00,
            'image_url' => 'https://example.com/img.jpg',
        ]);
    }

    public function test_create_skips_image_attachment_when_download_fails(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('setSku')->willReturnSelf();
        $product->method('setName')->willReturnSelf();
        $product->method('setPrice')->willReturnSelf();
        $product->method('setAttributeSetId')->willReturnSelf();
        $product->method('setStatus')->willReturnSelf();
        $product->method('setVisibility')->willReturnSelf();
        $product->method('setTypeId')->willReturnSelf();
        $product->method('setWeight')->willReturnSelf();
        $product->expects($this->never())->method('addImageToMediaGallery');

        $imageDownloader = $this->createMock(ImageDownloader::class);
        $imageDownloader->method('download')->willReturn(null);

        $directoryList = $this->createMock(DirectoryList::class);
        $directoryList->method('getPath')
            ->with(DirectoryList::MEDIA)
            ->willReturn('/var/www/html/pub/media');

        $factory = $this->createMock(ProductInterfaceFactory::class);
        $factory->method('create')->willReturn($product);

        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('setQty')->willReturnSelf();
        $stockItem->method('setIsInStock')->willReturnSelf();

        $stockRegistry = $this->createMock(StockRegistryInterface::class);
        $stockRegistry->method('getStockItemBySku')->willReturn($stockItem);

        $handler = $this->createHandler(
            productFactory: $factory,
            stockRegistry: $stockRegistry,
            imageDownloader: $imageDownloader,
            directoryList: $directoryList,
        );

        $handler->create([
            'sku' => 'TEST-NOIMG',
            'name' => 'No Image Product',
            'price' => 10.00,
            'image_url' => 'https://example.com/broken.jpg',
        ]);
    }

    public function test_clean_deletes_all_products(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getSku')->willReturn('TEST-001');

        $searchResults = $this->createMock(ProductSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([$product]);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);

        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('setPageSize')->willReturnSelf();
        $searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('getList')->willReturn($searchResults);
        $repository->expects($this->once())->method('deleteById')->with('TEST-001');

        $handler = $this->createHandler(
            productRepository: $repository,
            searchCriteriaBuilder: $searchCriteriaBuilder,
        );

        $handler->clean();
    }

    public function test_clean_calls_review_creator_clean_seed_reviews_before_deleting_products(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getSku')->willReturn('SEED-001');

        $searchResults = $this->createMock(ProductSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([$product]);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);

        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('setPageSize')->willReturnSelf();
        $searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $callOrder = [];

        $reviewCreator = $this->createMock(ReviewCreator::class);
        $reviewCreator->expects($this->once())
            ->method('cleanSeedReviews')
            ->willReturnCallback(function () use (&$callOrder): void {
                $callOrder[] = 'cleanSeedReviews';
            });

        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('getList')->willReturn($searchResults);
        $repository->expects($this->once())
            ->method('deleteById')
            ->with('SEED-001')
            ->willReturnCallback(function () use (&$callOrder): bool {
                $callOrder[] = 'deleteById';
                return true;
            });

        $handler = $this->createHandler(
            productRepository: $repository,
            searchCriteriaBuilder: $searchCriteriaBuilder,
            reviewCreator: $reviewCreator,
        );

        $handler->clean();

        $this->assertSame(['cleanSeedReviews', 'deleteById'], $callOrder);
    }

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
        $builder->expects($this->once())->method('build')->with($product, $this->callback(fn ($d) => isset($d['sku'])));
        $builder->expects($this->once())->method('afterSave')->with($product, $this->callback(fn ($d) => isset($d['sku'])));

        $pool = new TypeBuilderPool(['configurable' => $builder]);

        $factory = $this->createMock(ProductInterfaceFactory::class);
        $factory->method('create')->willReturn($product);

        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('save')->willReturn($product);

        $handler = $this->createHandler(
            productFactory: $factory,
            productRepository: $repository,
            typeBuilderPool: $pool,
        );

        $handler->create(['sku' => 'CFG-001', 'name' => 'X', 'price' => 10.0, 'product_type' => 'configurable']);
    }

    public function test_create_throws_on_unknown_product_type(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('setSku')->willReturnSelf();
        $product->method('setName')->willReturnSelf();
        $product->method('setPrice')->willReturnSelf();
        $product->method('setAttributeSetId')->willReturnSelf();
        $product->method('setStatus')->willReturnSelf();
        $product->method('setVisibility')->willReturnSelf();
        $product->method('setWeight')->willReturnSelf();

        $factory = $this->createMock(ProductInterfaceFactory::class);
        $factory->method('create')->willReturn($product);

        $pool = new TypeBuilderPool([]); // empty pool

        $handler = $this->createHandler(productFactory: $factory, typeBuilderPool: $pool);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported product_type: bundle');

        $handler->create(['sku' => 'X', 'name' => 'X', 'price' => 1.0, 'product_type' => 'bundle']);
    }

    public function test_create_routes_reviews_to_review_creator(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('setSku')->willReturnSelf();
        $product->method('setName')->willReturnSelf();
        $product->method('setPrice')->willReturnSelf();
        $product->method('setAttributeSetId')->willReturnSelf();
        $product->method('setStatus')->willReturnSelf();
        $product->method('setVisibility')->willReturnSelf();
        $product->method('setWeight')->willReturnSelf();
        $product->method('setCustomAttribute')->willReturnSelf();
        $product->method('getId')->willReturn(42);

        $factory = $this->createMock(ProductInterfaceFactory::class);
        $factory->method('create')->willReturn($product);

        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('save')->willReturn($product);

        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('setQty')->willReturnSelf();
        $stockItem->method('setIsInStock')->willReturnSelf();

        $stockRegistry = $this->createMock(StockRegistryInterface::class);
        $stockRegistry->method('getStockItemBySku')->willReturn($stockItem);

        $reviewCreator = $this->createMock(ReviewCreator::class);
        $reviewCreator->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function (int $productId, array $spec): void {
                $this->assertSame(42, $productId);
                $this->assertArrayHasKey('rating', $spec);
            });

        $handler = $this->createHandler(
            productFactory: $factory,
            productRepository: $repository,
            stockRegistry: $stockRegistry,
            reviewCreator: $reviewCreator,
        );

        $handler->create([
            'sku' => 'REV-001',
            'name' => 'Reviewed product',
            'price' => 9.99,
            'reviews' => [
                ['nickname' => 'alice', 'title' => 'Great', 'detail' => 'Ok', 'rating' => 5],
                ['nickname' => 'bob',   'title' => 'Bad',   'detail' => 'Meh', 'rating' => 1],
            ],
        ]);
    }

    public function test_create_skips_review_creator_when_reviews_key_missing(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('setSku')->willReturnSelf();
        $product->method('setName')->willReturnSelf();
        $product->method('setPrice')->willReturnSelf();
        $product->method('setAttributeSetId')->willReturnSelf();
        $product->method('setStatus')->willReturnSelf();
        $product->method('setVisibility')->willReturnSelf();
        $product->method('setTypeId')->willReturnSelf();
        $product->method('setWeight')->willReturnSelf();

        $factory = $this->createMock(ProductInterfaceFactory::class);
        $factory->method('create')->willReturn($product);

        $reviewCreator = $this->createMock(ReviewCreator::class);
        $reviewCreator->expects($this->never())->method('create');

        $handler = $this->createHandler(
            productFactory: $factory,
            reviewCreator: $reviewCreator,
        );

        $handler->create(['sku' => 'NO-REV', 'name' => 'X', 'price' => 1.0]);
    }

    private function createHandler(
        ?ProductInterfaceFactory $productFactory = null,
        ?ProductRepositoryInterface $productRepository = null,
        ?SearchCriteriaBuilder $searchCriteriaBuilder = null,
        ?StockRegistryInterface $stockRegistry = null,
        ?ImageDownloader $imageDownloader = null,
        ?DirectoryList $directoryList = null,
        ?StockIndexerProcessor $stockIndexerProcessor = null,
        ?TypeBuilderPool $typeBuilderPool = null,
        ?ReviewCreator $reviewCreator = null,
    ): ProductHandler {
        if ($typeBuilderPool === null) {
            $builder = $this->createMock(TypeBuilderInterface::class);
            $typeBuilderPool = new TypeBuilderPool(['simple' => $builder]);
        }

        if ($stockRegistry === null) {
            $stockItem = $this->createMock(StockItemInterface::class);
            $stockItem->method('setQty')->willReturnSelf();
            $stockItem->method('setIsInStock')->willReturnSelf();
            $stockRegistry = $this->createMock(StockRegistryInterface::class);
            $stockRegistry->method('getStockItemBySku')->willReturn($stockItem);
        }

        return new ProductHandler(
            $productFactory ?? $this->createMock(ProductInterfaceFactory::class),
            $productRepository ?? $this->createMock(ProductRepositoryInterface::class),
            $searchCriteriaBuilder ?? $this->createMock(SearchCriteriaBuilder::class),
            $stockRegistry,
            $imageDownloader ?? $this->createMock(ImageDownloader::class),
            $directoryList ?? $this->createMock(DirectoryList::class),
            $stockIndexerProcessor ?? $this->createMock(StockIndexerProcessor::class),
            $typeBuilderPool,
            $reviewCreator ?? $this->createMock(ReviewCreator::class),
        );
    }
}
