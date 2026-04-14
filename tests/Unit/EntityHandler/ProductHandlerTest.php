<?php

declare(strict_types=1);

namespace DavidLambauer\Seeder\Test\Unit\EntityHandler;

use DavidLambauer\Seeder\EntityHandler\ProductHandler;
use DavidLambauer\Seeder\Service\ImageDownloader;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\Data\ProductSearchResultsInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
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
        $repository->expects($this->once())->method('save')->with($product);

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

    private function createHandler(
        ?ProductInterfaceFactory $productFactory = null,
        ?ProductRepositoryInterface $productRepository = null,
        ?SearchCriteriaBuilder $searchCriteriaBuilder = null,
        ?StockRegistryInterface $stockRegistry = null,
        ?ImageDownloader $imageDownloader = null,
        ?DirectoryList $directoryList = null,
    ): ProductHandler {
        return new ProductHandler(
            $productFactory ?? $this->createMock(ProductInterfaceFactory::class),
            $productRepository ?? $this->createMock(ProductRepositoryInterface::class),
            $searchCriteriaBuilder ?? $this->createMock(SearchCriteriaBuilder::class),
            $stockRegistry ?? $this->createMock(StockRegistryInterface::class),
            $imageDownloader ?? $this->createMock(ImageDownloader::class),
            $directoryList ?? $this->createMock(DirectoryList::class),
        );
    }
}
