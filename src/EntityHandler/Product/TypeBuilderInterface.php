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
