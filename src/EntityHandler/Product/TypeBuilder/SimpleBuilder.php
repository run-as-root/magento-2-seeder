<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler\Product\TypeBuilder;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Type;
use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilderInterface;

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
