<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler\Product\TypeBuilder;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Type;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilder\SimpleBuilder;

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
            ->with(Type::TYPE_SIMPLE);

        (new SimpleBuilder())->build($product, []);
    }

    public function test_after_save_is_noop(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->expects($this->never())->method($this->anything());

        (new SimpleBuilder())->afterSave($product, []);
    }
}
