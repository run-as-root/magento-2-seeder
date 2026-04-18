<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler\Product\TypeBuilder;

use Magento\Bundle\Api\Data\LinkInterfaceFactory;
use Magento\Bundle\Api\Data\OptionInterfaceFactory;
use Magento\Bundle\Api\ProductLinkManagementInterface;
use Magento\Bundle\Api\ProductOptionRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;
use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilderInterface;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;

class BundleBuilder implements TypeBuilderInterface
{
    private const OPTION_COUNT = 3;
    private const MIN_CHILDREN_PER_OPTION = 2;
    private const MAX_CHILDREN_PER_OPTION = 3;
    private const MIN_POOL_SIZE = self::OPTION_COUNT * self::MIN_CHILDREN_PER_OPTION;
    private const OPTION_TYPES = ['select', 'radio', 'checkbox'];

    public function __construct(
        private readonly OptionInterfaceFactory $optionFactory,
        private readonly LinkInterfaceFactory $linkFactory,
        private readonly ProductOptionRepositoryInterface $optionRepository,
        private readonly ProductLinkManagementInterface $linkManagement,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly FilterBuilder $filterBuilder,
        private readonly GeneratedDataRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getType(): string
    {
        return Type::TYPE_BUNDLE;
    }

    public function build(ProductInterface $product, array $data): void
    {
        $product->setTypeId(Type::TYPE_BUNDLE);

        // Dynamic pricing flags (catalog_product_entity_int).
        $product->setData('price_type', 0);
        $product->setData('sku_type', 0);
        $product->setData('weight_type', 0);
        $product->setData('price_view', 0);
        $product->setData('shipment_type', 0);
    }

    public function afterSave(ProductInterface $parent, array $data): void
    {
        $pool = $this->resolveSimpleSkuPool();

        if (count($pool) < self::MIN_POOL_SIZE) {
            $this->logger->warning(
                sprintf(
                    'BundleBuilder: insufficient simple products (%d found, %d required) for SKU %s; skipping options.',
                    count($pool),
                    self::MIN_POOL_SIZE,
                    (string) $parent->getSku()
                )
            );
        }

        if ($pool === []) {
            return;
        }

        $assignments = $this->planOptions($pool);

        foreach ($assignments as $index => $childSkus) {
            $option = $this->optionFactory->create();
            $option->setTitle('Option ' . ($index + 1));
            $option->setType(self::OPTION_TYPES[$index]);
            $option->setRequired($index === 0);
            $option->setSku((string) $parent->getSku());
            $option->setPosition($index);

            $this->optionRepository->save($parent, $option);
            $optionId = (int) $option->getOptionId();

            foreach ($childSkus as $childSku) {
                $link = $this->linkFactory->create();
                $link->setSku($childSku);
                $link->setQty(1);
                $link->setPriceType(1); // percent
                $link->setPrice(0.0);
                $link->setIsDefault(false);
                $link->setCanChangeQuantity(0);

                $this->linkManagement->addChild($parent, $optionId, $link);
            }
        }
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

        if (count($pool) >= self::MIN_POOL_SIZE) {
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
            ->setValue(Type::TYPE_SIMPLE)
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

    /**
     * Deterministically split the pool into up to 3 options with 2-3 children each,
     * rotating start positions so options don't all share the same children.
     *
     * @param list<string> $pool
     * @return list<list<string>>
     */
    private function planOptions(array $pool): array
    {
        $poolSize = count($pool);
        if ($poolSize < self::MIN_CHILDREN_PER_OPTION) {
            return [];
        }

        $maxOptions = min(self::OPTION_COUNT, intdiv($poolSize, self::MIN_CHILDREN_PER_OPTION));
        $assignments = [];

        for ($i = 0; $i < $maxOptions; $i++) {
            $childrenForThisOption = ($i % 2 === 0)
                ? self::MAX_CHILDREN_PER_OPTION
                : self::MIN_CHILDREN_PER_OPTION;

            $children = [];
            for ($j = 0; $j < $childrenForThisOption; $j++) {
                // Rotate start per option so options differ.
                $poolIndex = ($i * self::MIN_CHILDREN_PER_OPTION + $j) % $poolSize;
                $children[] = $pool[$poolIndex];
            }

            $assignments[] = $children;
        }

        return $assignments;
    }
}
