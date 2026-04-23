<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler\Product\TypeBuilder;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\ConfigurableProduct\Api\Data\OptionInterfaceFactory;
use Magento\ConfigurableProduct\Api\Data\OptionValueInterfaceFactory;
use Magento\ConfigurableProduct\Api\LinkManagementInterface;
use Magento\ConfigurableProduct\Api\OptionRepositoryInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Model\Config as EavConfig;
use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilderInterface;

class ConfigurableBuilder implements TypeBuilderInterface
{
    private const TYPE_CONFIGURABLE = 'configurable';
    private const COMBINATIONS_STASH_KEY = '_seeder_configurable_combinations';
    private const COLOR_LIMIT = 3;
    private const SIZE_LIMIT = 2;
    private const MISSING_ATTRS_MESSAGE = "Configurable product requires 'color' and 'size' attributes with options;"
        . ' one or both are missing on this install.';

    public function __construct(
        private readonly ProductInterfaceFactory $productFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EavConfig $eavConfig,
        private readonly LinkManagementInterface $linkManagement,
        private readonly OptionRepositoryInterface $optionRepository,
        private readonly OptionInterfaceFactory $optionFactory,
        private readonly OptionValueInterfaceFactory $optionValueFactory,
    ) {
    }

    public function getType(): string
    {
        return self::TYPE_CONFIGURABLE;
    }

    public function build(ProductInterface $product, array $data): void
    {
        $product->setTypeId(self::TYPE_CONFIGURABLE);

        $colorAttr = $this->eavConfig->getAttribute('catalog_product', 'color');
        $sizeAttr = $this->eavConfig->getAttribute('catalog_product', 'size');

        $colorOptions = $this->pickRealOptions($colorAttr, self::COLOR_LIMIT);
        $sizeOptions = $this->pickRealOptions($sizeAttr, self::SIZE_LIMIT);

        if ($colorOptions === [] || $sizeOptions === []) {
            throw new \RuntimeException(self::MISSING_ATTRS_MESSAGE);
        }

        $combos = [];
        foreach ($colorOptions as $color) {
            foreach ($sizeOptions as $size) {
                $combos[] = [
                    'color_id' => (int) $color->getValue(),
                    'color_label' => (string) $color->getLabel(),
                    'size_id' => (int) $size->getValue(),
                    'size_label' => (string) $size->getLabel(),
                ];
            }
        }

        $product->setData(self::COMBINATIONS_STASH_KEY, $combos);
    }

    public function afterSave(ProductInterface $parent, array $data): void
    {
        $combos = $parent->getData(self::COMBINATIONS_STASH_KEY) ?? [];
        if ($combos === []) {
            return;
        }

        $childSkus = [];
        foreach ($combos as $combo) {
            $childSkus[] = $this->createChild($parent, $combo);
        }

        foreach ($childSkus as $childSku) {
            $this->linkManagement->addChild($parent->getSku(), $childSku);
        }

        $colorAttr = $this->eavConfig->getAttribute('catalog_product', 'color');
        $sizeAttr = $this->eavConfig->getAttribute('catalog_product', 'size');

        $colorIds = array_values(array_unique(array_map(static fn (array $c) => $c['color_id'], $combos)));
        $sizeIds = array_values(array_unique(array_map(static fn (array $c) => $c['size_id'], $combos)));

        $this->registerOption($parent->getSku(), $colorAttr, $colorIds, 0);
        $this->registerOption($parent->getSku(), $sizeAttr, $sizeIds, 1);
    }

    /**
     * @return \Magento\Eav\Api\Data\AttributeOptionInterface[]
     */
    private function pickRealOptions(AttributeInterface $attribute, int $limit): array
    {
        $options = $attribute->getOptions();
        $real = [];
        foreach ($options as $option) {
            $value = $option->getValue();
            if ($value === null || $value === '') {
                continue;
            }
            $real[] = $option;
            if (count($real) === $limit) {
                break;
            }
        }

        if (count($real) < $limit) {
            return [];
        }

        return $real;
    }

    private function createChild(ProductInterface $parent, array $combo): string
    {
        $child = $this->productFactory->create();

        $sku = $parent->getSku() . '-' . $this->slugify($combo['color_label'])
            . '-' . $this->slugify($combo['size_label']);
        $name = $parent->getName() . ' — ' . $combo['color_label'] . ' / ' . $combo['size_label'];

        $child->setSku($sku)
            ->setName($name)
            ->setPrice((float) $parent->getPrice())
            ->setTypeId(Type::TYPE_SIMPLE)
            ->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE)
            ->setStatus(1)
            ->setWeight(1.0)
            ->setAttributeSetId((int) $parent->getAttributeSetId())
            ->setCustomAttribute('color', $combo['color_id'])
            ->setCustomAttribute('size', $combo['size_id']);

        if (method_exists($child, 'setStockData')) {
            $child->setStockData([
                'use_config_manage_stock' => 1,
                'manage_stock' => 1,
                'is_in_stock' => 1,
                'qty' => 100,
            ]);
        }
        if (method_exists($child, 'setWebsiteIds')) {
            $child->setWebsiteIds([1]);
        }

        $this->productRepository->save($child);

        return $sku;
    }

    /**
     * @param int[] $valueIndexes
     */
    private function registerOption(
        string $parentSku,
        AttributeInterface $attribute,
        array $valueIndexes,
        int $position
    ): void {
        $option = $this->optionFactory->create();
        $option->setAttributeId((int) $attribute->getAttributeId());
        $option->setLabel((string) ($attribute->getDefaultFrontendLabel() ?: $attribute->getFrontendLabel()));
        $option->setPosition($position);
        $option->setIsUseDefault(true);

        $values = [];
        foreach ($valueIndexes as $id) {
            $value = $this->optionValueFactory->create();
            $value->setValueIndex($id);
            $values[] = $value;
        }
        $option->setValues($values);

        $this->optionRepository->save($parentSku, $option);
    }

    private function slugify(string $label): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $label) ?? $label;
        $slug = strtolower(trim($slug, '-'));

        return $slug === '' ? 'opt' : $slug;
    }
}
