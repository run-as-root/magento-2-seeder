<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler\Product\TypeBuilder;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductLinkInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;
use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilderInterface;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;

class GroupedBuilder implements TypeBuilderInterface
{
    private const TYPE_GROUPED = 'grouped';
    private const TYPE_SIMPLE = 'simple';
    private const MAX_LINKS = 5;
    private const LINK_TYPE_ASSOCIATED = 'associated';
    private const LINKED_PRODUCT_TYPE = 'simple';

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductLinkInterfaceFactory $productLinkFactory,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly FilterBuilder $filterBuilder,
        private readonly GeneratedDataRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getType(): string
    {
        return self::TYPE_GROUPED;
    }

    public function build(ProductInterface $product, array $data): void
    {
        $product->setTypeId(self::TYPE_GROUPED);
    }

    public function afterSave(ProductInterface $parent, array $data): void
    {
        $pool = $this->resolveSimpleSkuPool();

        if ($pool === []) {
            $this->logger->warning('GroupedBuilder: no simple products available to link');

            return;
        }

        $childSkus = array_slice($pool, 0, self::MAX_LINKS);
        $parentSku = (string) $parent->getSku();

        $links = [];
        foreach ($childSkus as $position => $childSku) {
            $link = $this->productLinkFactory->create();
            $link->setSku($parentSku);
            $link->setLinkedProductSku($childSku);
            $link->setLinkType(self::LINK_TYPE_ASSOCIATED);
            $link->setPosition($position);
            $link->setLinkedProductType(self::LINKED_PRODUCT_TYPE);

            $links[] = $link;
        }

        $parent->setProductLinks($links);
        $this->productRepository->save($parent);
    }

    /**
     * @return list<string>
     */
    private function resolveSimpleSkuPool(): array
    {
        $pool = [];

        foreach ($this->registry->getAll('product') as $entry) {
            $type = $entry['product_type'] ?? 'simple';
            if ($type !== 'simple') {
                continue;
            }
            $sku = $entry['sku'] ?? null;
            if (is_string($sku) && $sku !== '') {
                $pool[] = $sku;
            }
        }

        if (count($pool) >= self::MAX_LINKS) {
            return $pool;
        }

        $pool = array_values(array_unique(array_merge($pool, $this->loadSeedSkusFromDatabase())));

        return $pool;
    }

    /**
     * @return list<string>
     */
    private function loadSeedSkusFromDatabase(): array
    {
        $skuFilter = $this->filterBuilder
            ->setField('sku')
            ->setValue('SEED-%')
            ->setConditionType('like')
            ->create();

        $typeFilter = $this->filterBuilder
            ->setField('type_id')
            ->setValue(self::TYPE_SIMPLE)
            ->setConditionType('eq')
            ->create();

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilters([$skuFilter])
            ->addFilters([$typeFilter])
            ->create();

        $results = $this->productRepository->getList($searchCriteria);

        $skus = [];
        foreach ($results->getItems() as $product) {
            $sku = $product->getSku();
            if (is_string($sku) && $sku !== '') {
                $skus[] = $sku;
            }
        }

        return $skus;
    }
}
