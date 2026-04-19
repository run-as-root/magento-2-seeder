<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler\Product\TypeBuilder;

use Magento\Bundle\Api\Data\LinkInterface;
use Magento\Bundle\Api\Data\LinkInterfaceFactory;
use Magento\Bundle\Api\Data\OptionInterface;
use Magento\Bundle\Api\Data\OptionInterfaceFactory;
use Magento\Bundle\Api\ProductLinkManagementInterface;
use Magento\Bundle\Api\ProductOptionRepositoryInterface;
use Magento\Catalog\Api\Data\ProductSearchResultsInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilder\BundleBuilder;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;

final class BundleBuilderTest extends TestCase
{
    private OptionInterfaceFactory&MockObject $optionFactory;
    private LinkInterfaceFactory&MockObject $linkFactory;
    private ProductOptionRepositoryInterface&MockObject $optionRepository;
    private ProductLinkManagementInterface&MockObject $linkManagement;
    private ProductRepositoryInterface&MockObject $productRepository;
    private SearchCriteriaBuilder&MockObject $searchCriteriaBuilder;
    private FilterBuilder&MockObject $filterBuilder;
    private GeneratedDataRegistry $registry;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->optionFactory = $this->createMock(OptionInterfaceFactory::class);
        $this->linkFactory = $this->createMock(LinkInterfaceFactory::class);
        $this->optionRepository = $this->createMock(ProductOptionRepositoryInterface::class);
        $this->linkManagement = $this->createMock(ProductLinkManagementInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->filterBuilder = $this->createMock(FilterBuilder::class);
        $this->registry = new GeneratedDataRegistry();
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function test_get_type_returns_bundle(): void
    {
        $this->assertSame('bundle', $this->newBuilder()->getType());
    }

    public function test_build_sets_type_id_and_dynamic_flags(): void
    {
        $product = $this->createMock(Product::class);
        $product->expects($this->once())->method('setTypeId')->with('bundle');

        $capturedData = [];
        $product->method('setData')->willReturnCallback(
            function ($key, $value = null) use (&$capturedData, $product) {
                $capturedData[$key] = $value;

                return $product;
            }
        );

        $this->newBuilder()->build($product, []);

        $this->assertSame(0, $capturedData['price_type']);
        $this->assertSame(0, $capturedData['sku_type']);
        $this->assertSame(0, $capturedData['weight_type']);
        $this->assertSame(0, $capturedData['price_view']);
        $this->assertSame(0, $capturedData['shipment_type']);
    }

    public function test_after_save_uses_registry_simples_when_available(): void
    {
        for ($i = 1; $i <= 9; $i++) {
            $this->registry->add('product', ['sku' => "SEED-{$i}", 'product_type' => 'simple']);
        }

        $parent = $this->createMock(Product::class);
        $parent->method('getSku')->willReturn('BUNDLE-1');

        $this->stubOptionAndLinkFactories();

        $this->optionRepository->expects($this->exactly(3))
            ->method('save')
            ->willReturn(100);

        $addChildCallCount = 0;
        $this->linkManagement->method('addChild')->willReturnCallback(
            function () use (&$addChildCallCount) {
                $addChildCallCount++;

                return 1;
            }
        );

        $this->newBuilder()->afterSave($parent, []);

        $this->assertGreaterThanOrEqual(6, $addChildCallCount);
    }

    public function test_after_save_falls_back_to_database_when_registry_is_thin(): void
    {
        $this->registry->add('product', ['sku' => 'SEED-1', 'product_type' => 'simple']);
        $this->registry->add('product', ['sku' => 'SEED-2', 'product_type' => 'simple']);

        $dbProducts = [];
        for ($i = 3; $i <= 7; $i++) {
            $p = $this->createMock(Product::class);
            $p->method('getSku')->willReturn("SEED-{$i}");
            $dbProducts[] = $p;
        }

        $this->stubSearchCriteria();

        $searchResults = $this->createMock(ProductSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn($dbProducts);
        $this->productRepository->method('getList')->willReturn($searchResults);

        $parent = $this->createMock(Product::class);
        $parent->method('getSku')->willReturn('BUNDLE-1');

        $this->stubOptionAndLinkFactories();

        $this->optionRepository->expects($this->exactly(3))
            ->method('save')
            ->willReturn(200);

        $addChildCallCount = 0;
        $this->linkManagement->method('addChild')->willReturnCallback(
            function () use (&$addChildCallCount) {
                $addChildCallCount++;

                return 1;
            }
        );

        $this->newBuilder()->afterSave($parent, []);

        $this->assertGreaterThanOrEqual(6, $addChildCallCount);
    }

    public function test_after_save_skips_options_when_pool_is_empty(): void
    {
        $this->stubSearchCriteria();

        $searchResults = $this->createMock(ProductSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([]);
        $this->productRepository->method('getList')->willReturn($searchResults);

        $parent = $this->createMock(Product::class);
        $parent->method('getSku')->willReturn('BUNDLE-1');

        $this->optionRepository->expects($this->never())->method('save');
        $this->linkManagement->expects($this->never())->method('addChild');

        $this->logger->expects($this->once())->method('warning');

        $this->newBuilder()->afterSave($parent, []);
    }

    public function test_option_types_rotate_select_radio_checkbox(): void
    {
        for ($i = 1; $i <= 9; $i++) {
            $this->registry->add('product', ['sku' => "SEED-{$i}", 'product_type' => 'simple']);
        }

        $parent = $this->createMock(Product::class);
        $parent->method('getSku')->willReturn('BUNDLE-1');

        $capturedTypes = [];
        $this->optionFactory->method('create')->willReturnCallback(
            function () use (&$capturedTypes) {
                $option = $this->createMock(OptionInterface::class);
                $option->method('setTitle')->willReturnSelf();
                $option->method('setRequired')->willReturnSelf();
                $option->method('setSku')->willReturnSelf();
                $option->method('setPosition')->willReturnSelf();
                $option->method('getOptionId')->willReturn(1);
                $option->method('setType')->willReturnCallback(
                    function ($type) use ($option, &$capturedTypes) {
                        $capturedTypes[] = $type;

                        return $option;
                    }
                );

                return $option;
            }
        );

        $this->linkFactory->method('create')->willReturnCallback(
            fn () => $this->stubLink()
        );

        $this->optionRepository->method('save')->willReturn(100);
        $this->linkManagement->method('addChild')->willReturn(1);

        $this->newBuilder()->afterSave($parent, []);

        $this->assertSame(['select', 'radio', 'checkbox'], $capturedTypes);
    }

    public function test_first_option_is_required_others_are_not(): void
    {
        for ($i = 1; $i <= 9; $i++) {
            $this->registry->add('product', ['sku' => "SEED-{$i}", 'product_type' => 'simple']);
        }

        $parent = $this->createMock(Product::class);
        $parent->method('getSku')->willReturn('BUNDLE-1');

        $capturedRequired = [];
        $this->optionFactory->method('create')->willReturnCallback(
            function () use (&$capturedRequired) {
                $option = $this->createMock(OptionInterface::class);
                $option->method('setTitle')->willReturnSelf();
                $option->method('setType')->willReturnSelf();
                $option->method('setSku')->willReturnSelf();
                $option->method('setPosition')->willReturnSelf();
                $option->method('getOptionId')->willReturn(1);
                $option->method('setRequired')->willReturnCallback(
                    function ($required) use ($option, &$capturedRequired) {
                        $capturedRequired[] = $required;

                        return $option;
                    }
                );

                return $option;
            }
        );

        $this->linkFactory->method('create')->willReturnCallback(
            fn () => $this->stubLink()
        );

        $this->optionRepository->method('save')->willReturn(100);
        $this->linkManagement->method('addChild')->willReturn(1);

        $this->newBuilder()->afterSave($parent, []);

        $this->assertSame([true, false, false], $capturedRequired);
    }

    private function newBuilder(): BundleBuilder
    {
        return new BundleBuilder(
            $this->optionFactory,
            $this->linkFactory,
            $this->optionRepository,
            $this->linkManagement,
            $this->productRepository,
            $this->searchCriteriaBuilder,
            $this->filterBuilder,
            $this->registry,
            $this->logger,
        );
    }

    private function stubOptionAndLinkFactories(): void
    {
        $this->optionFactory->method('create')->willReturnCallback(
            function () {
                $option = $this->createMock(OptionInterface::class);
                $option->method('setTitle')->willReturnSelf();
                $option->method('setType')->willReturnSelf();
                $option->method('setRequired')->willReturnSelf();
                $option->method('setSku')->willReturnSelf();
                $option->method('setPosition')->willReturnSelf();
                $option->method('getOptionId')->willReturn(1);

                return $option;
            }
        );

        $this->linkFactory->method('create')->willReturnCallback(
            fn () => $this->stubLink()
        );
    }

    private function stubLink(): LinkInterface
    {
        $link = $this->createMock(LinkInterface::class);
        $link->method('setSku')->willReturnSelf();
        $link->method('setQty')->willReturnSelf();
        $link->method('setPriceType')->willReturnSelf();
        $link->method('setPrice')->willReturnSelf();
        $link->method('setIsDefault')->willReturnSelf();
        $link->method('setCanChangeQuantity')->willReturnSelf();

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
