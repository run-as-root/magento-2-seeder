<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler\Product\TypeBuilder;

use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Api\Data\OptionInterface;
use Magento\ConfigurableProduct\Api\Data\OptionInterfaceFactory;
use Magento\ConfigurableProduct\Api\Data\OptionValueInterface;
use Magento\ConfigurableProduct\Api\Data\OptionValueInterfaceFactory;
use Magento\ConfigurableProduct\Api\LinkManagementInterface;
use Magento\ConfigurableProduct\Api\OptionRepositoryInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Eav\Model\Config as EavConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilder\ConfigurableBuilder;

final class ConfigurableBuilderTest extends TestCase
{

    private ProductInterfaceFactory&MockObject $productFactory;
    private ProductRepositoryInterface&MockObject $productRepository;
    private EavConfig&MockObject $eavConfig;
    private LinkManagementInterface&MockObject $linkManagement;
    private OptionRepositoryInterface&MockObject $optionRepository;
    private OptionInterfaceFactory&MockObject $optionFactory;
    private OptionValueInterfaceFactory&MockObject $optionValueFactory;

    protected function setUp(): void
    {
        $this->productFactory = $this->createMock(ProductInterfaceFactory::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->eavConfig = $this->createMock(EavConfig::class);
        $this->linkManagement = $this->createMock(LinkManagementInterface::class);
        $this->optionRepository = $this->createMock(OptionRepositoryInterface::class);
        $this->optionFactory = $this->createMock(OptionInterfaceFactory::class);
        $this->optionValueFactory = $this->createMock(OptionValueInterfaceFactory::class);
    }

    public function test_get_type_returns_configurable(): void
    {
        $this->assertSame('configurable', $this->newBuilder()->getType());
    }

    public function test_build_sets_type_id_configurable(): void
    {
        $this->stubColorAndSizeAttributes();

        $product = $this->createMock(Product::class);
        $product->expects($this->once())->method('setTypeId')->with('configurable');

        $this->newBuilder()->build($product, []);
    }

    public function test_build_throws_when_color_attribute_missing(): void
    {
        $colorAttr = $this->createAttributeMock(93, 'color', 'Color', []);
        $sizeAttr = $this->createAttributeMock(94, 'size', 'Size', [
            $this->createOptionMock('42', 'S'),
            $this->createOptionMock('43', 'M'),
        ]);
        $this->eavConfig->method('getAttribute')->willReturnCallback(
            fn (string $entity, string $code) => $code === 'color' ? $colorAttr : $sizeAttr
        );

        $product = $this->createMock(Product::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Configurable product requires 'color' and 'size' attributes with options; "
            . 'one or both are missing on this install.'
        );

        $this->newBuilder()->build($product, []);
    }

    public function test_build_throws_when_size_attribute_missing(): void
    {
        $colorAttr = $this->createAttributeMock(93, 'color', 'Color', [
            $this->createOptionMock('10', 'Red'),
            $this->createOptionMock('11', 'Green'),
            $this->createOptionMock('12', 'Blue'),
        ]);
        $sizeAttr = $this->createAttributeMock(94, 'size', 'Size', []);
        $this->eavConfig->method('getAttribute')->willReturnCallback(
            fn (string $entity, string $code) => $code === 'color' ? $colorAttr : $sizeAttr
        );

        $product = $this->createMock(Product::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Configurable product requires 'color' and 'size' attributes with options; "
            . 'one or both are missing on this install.'
        );

        $this->newBuilder()->build($product, []);
    }

    public function test_build_stashes_6_combinations(): void
    {
        $this->stubColorAndSizeAttributes();

        $product = $this->createMock(Product::class);

        $capturedCombos = null;
        $product->method('setData')->willReturnCallback(
            function ($key, $value = null) use (&$capturedCombos, $product) {
                if ($key === '_seeder_configurable_combinations') {
                    $capturedCombos = $value;
                }

                return $product;
            }
        );

        $this->newBuilder()->build($product, []);

        $this->assertIsArray($capturedCombos);
        $this->assertCount(6, $capturedCombos);
        foreach ($capturedCombos as $combo) {
            $this->assertArrayHasKey('color_id', $combo);
            $this->assertArrayHasKey('color_label', $combo);
            $this->assertArrayHasKey('size_id', $combo);
            $this->assertArrayHasKey('size_label', $combo);
        }
    }

    public function test_after_save_creates_6_children_and_links_them(): void
    {
        $combos = [
            ['color_id' => 10, 'color_label' => 'Red', 'size_id' => 20, 'size_label' => 'S'],
            ['color_id' => 10, 'color_label' => 'Red', 'size_id' => 21, 'size_label' => 'M'],
            ['color_id' => 11, 'color_label' => 'Green', 'size_id' => 20, 'size_label' => 'S'],
            ['color_id' => 11, 'color_label' => 'Green', 'size_id' => 21, 'size_label' => 'M'],
            ['color_id' => 12, 'color_label' => 'Blue', 'size_id' => 20, 'size_label' => 'S'],
            ['color_id' => 12, 'color_label' => 'Blue', 'size_id' => 21, 'size_label' => 'M'],
        ];

        $parent = $this->createMock(Product::class);
        $parent->method('getSku')->willReturn('PARENT-SKU');
        $parent->method('getName')->willReturn('Parent Name');
        $parent->method('getPrice')->willReturn(99.99);
        $parent->method('getAttributeSetId')->willReturn(4);
        $parent->method('getData')->willReturnCallback(
            fn ($key = '', $index = null) => $key === '_seeder_configurable_combinations' ? $combos : null
        );

        $this->productRepository->expects($this->exactly(6))
            ->method('save')
            ->willReturnCallback(fn ($child) => $child);

        $expectedChildSkus = [
            'PARENT-SKU-red-s',
            'PARENT-SKU-red-m',
            'PARENT-SKU-green-s',
            'PARENT-SKU-green-m',
            'PARENT-SKU-blue-s',
            'PARENT-SKU-blue-m',
        ];

        // Track the SKUs set on children by intercepting productFactory+setSku
        $capturedChildSkus = [];
        $this->productFactory->method('create')->willReturnCallback(function () use (&$capturedChildSkus) {
            $child = $this->createProductMock();
            $child->method('setSku')->willReturnCallback(function ($sku) use ($child, &$capturedChildSkus) {
                $capturedChildSkus[] = $sku;

                return $child;
            });
            $child->method('setName')->willReturnSelf();
            $child->method('setPrice')->willReturnSelf();
            $child->method('setTypeId')->willReturnSelf();
            $child->method('setVisibility')->willReturnSelf();
            $child->method('setStatus')->willReturnSelf();
            $child->method('setWeight')->willReturnSelf();
            $child->method('setAttributeSetId')->willReturnSelf();
            $child->method('setCustomAttribute')->willReturnSelf();
            $child->method('setStockData')->willReturnSelf();
            $child->method('setWebsiteIds')->willReturnSelf();

            return $child;
        });

        $addedChildSkus = [];
        $this->linkManagement->expects($this->exactly(count($expectedChildSkus)))
            ->method('addChild')
            ->willReturnCallback(function (string $sku, string $childSku) use (&$addedChildSkus): int {
                $this->assertSame('PARENT-SKU', $sku);
                $addedChildSkus[] = $childSku;
                return 1;
            });

        // Stub EavConfig + option factories for the option registration path.
        $this->stubColorAndSizeAttributes();
        $this->optionFactory->method('create')->willReturnCallback(
            fn () => $this->createMock(OptionInterface::class)
        );
        $this->optionValueFactory->method('create')->willReturnCallback(
            fn () => $this->createMock(OptionValueInterface::class)
        );

        $this->newBuilder()->afterSave($parent, []);

        $this->assertSame($expectedChildSkus, $capturedChildSkus);
    }

    public function test_after_save_registers_color_and_size_options(): void
    {
        $combos = [
            ['color_id' => 10, 'color_label' => 'Red', 'size_id' => 20, 'size_label' => 'S'],
            ['color_id' => 10, 'color_label' => 'Red', 'size_id' => 21, 'size_label' => 'M'],
            ['color_id' => 11, 'color_label' => 'Green', 'size_id' => 20, 'size_label' => 'S'],
            ['color_id' => 11, 'color_label' => 'Green', 'size_id' => 21, 'size_label' => 'M'],
            ['color_id' => 12, 'color_label' => 'Blue', 'size_id' => 20, 'size_label' => 'S'],
            ['color_id' => 12, 'color_label' => 'Blue', 'size_id' => 21, 'size_label' => 'M'],
        ];

        $parent = $this->createMock(Product::class);
        $parent->method('getSku')->willReturn('PARENT-SKU');
        $parent->method('getName')->willReturn('Parent Name');
        $parent->method('getPrice')->willReturn(42.0);
        $parent->method('getAttributeSetId')->willReturn(4);
        $parent->method('getData')->willReturnCallback(
            fn ($key = '', $index = null) => $key === '_seeder_configurable_combinations' ? $combos : null
        );

        $this->productFactory->method('create')->willReturnCallback(function () {
            $child = $this->createProductMock();
            $child->method('setSku')->willReturnSelf();
            $child->method('setName')->willReturnSelf();
            $child->method('setPrice')->willReturnSelf();
            $child->method('setTypeId')->willReturnSelf();
            $child->method('setVisibility')->willReturnSelf();
            $child->method('setStatus')->willReturnSelf();
            $child->method('setWeight')->willReturnSelf();
            $child->method('setAttributeSetId')->willReturnSelf();
            $child->method('setCustomAttribute')->willReturnSelf();
            $child->method('setStockData')->willReturnSelf();
            $child->method('setWebsiteIds')->willReturnSelf();

            return $child;
        });

        $this->productRepository->method('save')->willReturnCallback(fn ($c) => $c);
        $this->linkManagement->method('addChild')->willReturn(1);

        $this->stubColorAndSizeAttributes();
        $this->optionFactory->method('create')->willReturnCallback(
            fn () => $this->createMock(OptionInterface::class)
        );
        $this->optionValueFactory->method('create')->willReturnCallback(
            fn () => $this->createMock(OptionValueInterface::class)
        );

        $this->optionRepository->expects($this->exactly(2))
            ->method('save')
            ->with('PARENT-SKU', $this->isInstanceOf(OptionInterface::class))
            ->willReturn(1);

        $this->newBuilder()->afterSave($parent, []);
    }

    private function newBuilder(): ConfigurableBuilder
    {
        return new ConfigurableBuilder(
            $this->productFactory,
            $this->productRepository,
            $this->eavConfig,
            $this->linkManagement,
            $this->optionRepository,
            $this->optionFactory,
            $this->optionValueFactory,
        );
    }

    private function stubColorAndSizeAttributes(): void
    {
        $colorAttr = $this->createAttributeMock(93, 'color', 'Color', [
            $this->createOptionMock('10', 'Red'),
            $this->createOptionMock('11', 'Green'),
            $this->createOptionMock('12', 'Blue'),
            $this->createOptionMock('13', 'Yellow'),
        ]);
        $sizeAttr = $this->createAttributeMock(94, 'size', 'Size', [
            $this->createOptionMock('20', 'S'),
            $this->createOptionMock('21', 'M'),
            $this->createOptionMock('22', 'L'),
        ]);
        $this->eavConfig->method('getAttribute')->willReturnCallback(
            fn (string $entity, string $code) => $code === 'color' ? $colorAttr : $sizeAttr
        );
    }

    private function createAttributeMock(
        int $id,
        string $code,
        string $label,
        array $options
    ): AttributeInterface&MockObject {
        $attr = $this->getMockBuilder(AttributeInterface::class)
            ->disableOriginalConstructor()
            ->addMethods(['getFrontendLabel'])
            ->getMockForAbstractClass();
        $attr->method('getAttributeId')->willReturn($id);
        $attr->method('getAttributeCode')->willReturn($code);
        $attr->method('getDefaultFrontendLabel')->willReturn($label);
        $attr->method('getFrontendLabel')->willReturn($label);
        $attr->method('getOptions')->willReturn($options);

        return $attr;
    }

    private function createOptionMock(string $value, string $label): AttributeOptionInterface&MockObject
    {
        $opt = $this->createMock(AttributeOptionInterface::class);
        $opt->method('getValue')->willReturn($value);
        $opt->method('getLabel')->willReturn($label);

        return $opt;
    }
    private function createProductMock(): Product&MockObject
    {
        return $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getId', 'getSku', 'setSku', 'getName', 'setName',
                'getPrice', 'setPrice', 'getAttributeSetId', 'setAttributeSetId',
                'getStatus', 'setStatus', 'getVisibility', 'setVisibility',
                'getTypeId', 'setTypeId', 'getWeight', 'setWeight',
                'setCustomAttribute', 'setProductLinks',
                'setData', 'getData', 'addImageToMediaGallery',
                'setStockData',
            ])
            ->addMethods(['setWebsiteIds'])
            ->getMock();
    }

}
