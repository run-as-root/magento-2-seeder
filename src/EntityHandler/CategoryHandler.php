<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler;

use RunAsRoot\Seeder\Api\EntityHandlerInterface;
use Magento\Catalog\Api\CategoryListInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterfaceFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;

class CategoryHandler implements EntityHandlerInterface
{
    private const PROTECTED_CATEGORY_IDS = [1, 2];

    public function __construct(
        private readonly CategoryInterfaceFactory $categoryFactory,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CategoryListInterface $categoryList,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
    ) {
    }

    public function getType(): string
    {
        return 'category';
    }

    public function create(array $data): int
    {
        $category = $this->categoryFactory->create();
        $category->setName($data['name']);
        $category->setIsActive($data['is_active'] ?? true);
        $category->setParentId($data['parent_id'] ?? 2);

        if (isset($data['description'])) {
            $category->setCustomAttribute('description', $data['description']);
        }

        if (isset($data['url_key'])) {
            $category->setCustomAttribute('url_key', $data['url_key']);
        }

        $savedCategory = $this->categoryRepository->save($category);

        return (int) $savedCategory->getId();
    }

    public function clean(): void
    {
        $this->searchCriteriaBuilder->addFilter(
            'entity_id',
            self::PROTECTED_CATEGORY_IDS,
            'nin'
        );

        $searchCriteria = $this->searchCriteriaBuilder->setPageSize(10000)->create();
        $categories = $this->categoryList->getList($searchCriteria);

        foreach ($categories->getItems() as $category) {
            $this->categoryRepository->deleteByIdentifier($category->getId());
        }
    }
}
