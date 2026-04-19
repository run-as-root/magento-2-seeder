<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler\Product\TypeBuilder;

use Magento\Catalog\Api\Data\ProductLinkInterface;
use Magento\Catalog\Api\Data\ProductLinkInterfaceFactory;
use Magento\Catalog\Api\Data\ProductSearchResultsInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilder\GroupedBuilder;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;

final class GroupedBuilderTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $productRepository;
    private ProductLinkInterfaceFactory&MockObject $productLinkFactory;
    private SearchCriteriaBuilder&MockObject $searchCriteriaBuilder;
    private FilterBuilder&MockObject $filterBuilder;
    private GeneratedDataRegistry $registry;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->productLinkFactory = $this->createMock(ProductLinkInterfaceFactory::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->filterBuilder = $this->createMock(FilterBuilder::class);
        $this->registry = new GeneratedDataRegistry();
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function test_get_type_returns_grouped(): void
    {
        $this->assertSame('grouped', $this->newBuilder()->getType());
    }

    public function test_build_sets_type_id_grouped(): void
    {
        $product = $this->createMock(Product::class);
        $product->expects($this->once())->method('setTypeId')->with('grouped');

        $this->newBuilder()->build($product, []);
    }

    public function test_after_save_links_5_simples_from_registry(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            $this->registry->add('product', ['sku' => "SEED-{$i}", 'product_type' => 'simple']);
        }

        $parent = $this->createMock(Product::class);
        $parent->method('getSku')->willReturn('GRP-001');

        $createCount = 0;
        $this->productLinkFactory->method('create')->willReturnCallback(
            function () use (&$createCount) {
                $createCount++;

                return $this->stubLink();
            }
        );

        $capturedLinks = null;
        $parent->expects($this->once())
            ->method('setProductLinks')
            ->willReturnCallback(
                function (array $links) use (&$capturedLinks, $parent) {
                    $capturedLinks = $links;

                    return $parent;
                }
            );

        $this->productRepository->expects($this->once())
            ->method('save')
            ->with($parent);

        $this->newBuilder()->afterSave($parent, []);

        $this->assertSame(5, $createCount);
        $this->assertIsArray($capturedLinks);
        $this->assertCount(5, $capturedLinks);
    }

    public function test_after_save_falls_back_to_database_when_registry_is_empty(): void
    {
        $dbProducts = [];
        for ($i = 1; $i <= 6; $i++) {
            $p = $this->createMock(Product::class);
            $p->method('getSku')->willReturn("SEED-{$i}");
            $dbProducts[] = $p;
        }

        $this->stubSearchCriteria();

        $searchResults = $this->createMock(ProductSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn($dbProducts);
        $this->productRepository->method('getList')->willReturn($searchResults);

        $parent = $this->createMock(Product::class);
        $parent->method('getSku')->willReturn('GRP-001');

        $createCount = 0;
        $this->productLinkFactory->method('create')->willReturnCallback(
            function () use (&$createCount) {
                $createCount++;

                return $this->stubLink();
            }
        );

        $parent->expects($this->once())->method('setProductLinks')->willReturnSelf();
        $this->productRepository->expects($this->once())->method('save')->with($parent);

        $this->newBuilder()->afterSave($parent, []);

        $this->assertSame(5, $createCount);
    }

    public function test_after_save_uses_fewer_than_5_when_pool_is_small(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->registry->add('product', ['sku' => "SEED-{$i}", 'product_type' => 'simple']);
        }

        $this->stubSearchCriteria();

        $searchResults = $this->createMock(ProductSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([]);
        $this->productRepository->method('getList')->willReturn($searchResults);

        $parent = $this->createMock(Product::class);
        $parent->method('getSku')->willReturn('GRP-001');

        $createCount = 0;
        $this->productLinkFactory->method('create')->willReturnCallback(
            function () use (&$createCount) {
                $createCount++;

                return $this->stubLink();
            }
        );

        $parent->expects($this->once())->method('setProductLinks')->willReturnSelf();
        $this->productRepository->expects($this->once())->method('save')->with($parent);

        $this->newBuilder()->afterSave($parent, []);

        $this->assertSame(3, $createCount);
    }

    public function test_after_save_warns_and_returns_when_pool_is_empty(): void
    {
        $this->stubSearchCriteria();

        $searchResults = $this->createMock(ProductSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([]);
        $this->productRepository->method('getList')->willReturn($searchResults);

        $parent = $this->createMock(Product::class);
        $parent->method('getSku')->willReturn('GRP-001');

        $this->productLinkFactory->expects($this->never())->method('create');
        $parent->expects($this->never())->method('setProductLinks');
        $this->productRepository->expects($this->never())->method('save');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('GroupedBuilder: no simple products available to link');

        $this->newBuilder()->afterSave($parent, []);
    }

    public function test_links_set_correct_from_and_to_skus_and_positions(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->registry->add('product', ['sku' => "SKU-{$i}", 'product_type' => 'simple']);
        }

        $parent = $this->createMock(Product::class);
        $parent->method('getSku')->willReturn('GRP-001');

        $capturedFromSkus = [];
        $capturedToSkus = [];
        $capturedLinkTypes = [];
        $capturedPositions = [];

        $this->productLinkFactory->method('create')->willReturnCallback(
            function () use (&$capturedFromSkus, &$capturedToSkus, &$capturedLinkTypes, &$capturedPositions) {
                $link = $this->createMock(ProductLinkInterface::class);
                $link->method('setSku')->willReturnCallback(
                    function ($sku) use ($link, &$capturedFromSkus) {
                        $capturedFromSkus[] = $sku;

                        return $link;
                    }
                );
                $link->method('setLinkedProductSku')->willReturnCallback(
                    function ($sku) use ($link, &$capturedToSkus) {
                        $capturedToSkus[] = $sku;

                        return $link;
                    }
                );
                $link->method('setLinkType')->willReturnCallback(
                    function ($type) use ($link, &$capturedLinkTypes) {
                        $capturedLinkTypes[] = $type;

                        return $link;
                    }
                );
                $link->method('setPosition')->willReturnCallback(
                    function ($position) use ($link, &$capturedPositions) {
                        $capturedPositions[] = $position;

                        return $link;
                    }
                );
                $link->method('setLinkedProductType')->willReturnSelf();

                return $link;
            }
        );

        $parent->method('setProductLinks')->willReturnSelf();

        $this->newBuilder()->afterSave($parent, []);

        $this->assertSame(['GRP-001', 'GRP-001', 'GRP-001', 'GRP-001', 'GRP-001'], $capturedFromSkus);
        $this->assertSame(['SKU-1', 'SKU-2', 'SKU-3', 'SKU-4', 'SKU-5'], $capturedToSkus);
        $this->assertSame(
            ['associated', 'associated', 'associated', 'associated', 'associated'],
            $capturedLinkTypes
        );
        $this->assertSame([0, 1, 2, 3, 4], $capturedPositions);
    }

    private function newBuilder(): GroupedBuilder
    {
        return new GroupedBuilder(
            $this->productRepository,
            $this->productLinkFactory,
            $this->searchCriteriaBuilder,
            $this->filterBuilder,
            $this->registry,
            $this->logger,
        );
    }

    private function stubLink(): ProductLinkInterface
    {
        $link = $this->createMock(ProductLinkInterface::class);
        $link->method('setSku')->willReturnSelf();
        $link->method('setLinkedProductSku')->willReturnSelf();
        $link->method('setLinkType')->willReturnSelf();
        $link->method('setPosition')->willReturnSelf();
        $link->method('setLinkedProductType')->willReturnSelf();

        return $link;
    }

    private function stubSearchCriteria(): void
    {
        $this->filterBuilder->method('setField')->willReturnSelf();
        $this->filterBuilder->method('setValue')->willReturnSelf();
        $this->filterBuilder->method('setConditionType')->willReturnSelf();
        $this->filterBuilder->method('create')->willReturn(new \Magento\Framework\Api\Filter());

        $this->searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $this->searchCriteriaBuilder->method('addFilters')->willReturnSelf();
        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);
        $this->searchCriteriaBuilder->method('create')->willReturn($searchCriteria);
    }
}
