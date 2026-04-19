<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler;

use RunAsRoot\Seeder\EntityHandler\CategoryHandler;
use Magento\Catalog\Api\CategoryListInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\CategoryInterfaceFactory;
use Magento\Catalog\Api\Data\CategorySearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use PHPUnit\Framework\TestCase;

final class CategoryHandlerTest extends TestCase
{
    public function test_get_type_returns_category(): void
    {
        $handler = $this->createHandler();

        $this->assertSame('category', $handler->getType());
    }

    public function test_create_saves_category_via_repository(): void
    {
        $category = $this->createMock(CategoryInterface::class);
        $category->expects($this->once())->method('setName')->with('Test Category')->willReturnSelf();
        $category->expects($this->once())->method('setIsActive')->with(true)->willReturnSelf();
        $category->expects($this->once())->method('setParentId')->with(2)->willReturnSelf();

        $factory = $this->createMock(CategoryInterfaceFactory::class);
        $factory->method('create')->willReturn($category);

        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->expects($this->once())->method('save')->with($category);

        $handler = $this->createHandler(
            categoryFactory: $factory,
            categoryRepository: $repository,
        );

        $handler->create([
            'name' => 'Test Category',
            'is_active' => true,
            'parent_id' => 2,
        ]);
    }

    public function test_create_returns_saved_category_id(): void
    {
        $category = $this->createMock(CategoryInterface::class);
        $category->method('setName')->willReturnSelf();
        $category->method('setIsActive')->willReturnSelf();
        $category->method('setParentId')->willReturnSelf();

        $savedCategory = $this->createMock(CategoryInterface::class);
        $savedCategory->method('getId')->willReturn(42);

        $factory = $this->createMock(CategoryInterfaceFactory::class);
        $factory->method('create')->willReturn($category);

        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->method('save')->willReturn($savedCategory);

        $handler = $this->createHandler(
            categoryFactory: $factory,
            categoryRepository: $repository,
        );

        $id = $handler->create([
            'name' => 'Test Category',
            'is_active' => true,
            'parent_id' => 2,
        ]);

        $this->assertSame(42, $id);
    }

    public function test_clean_deletes_non_root_categories(): void
    {
        $category = $this->createMock(CategoryInterface::class);
        $category->method('getId')->willReturn(5);

        $searchResults = $this->createMock(CategorySearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([$category]);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);

        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $searchCriteriaBuilder->method('setPageSize')->willReturnSelf();
        $searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $categoryList = $this->createMock(CategoryListInterface::class);
        $categoryList->method('getList')->willReturn($searchResults);

        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->expects($this->once())->method('deleteByIdentifier')->with(5);

        $handler = $this->createHandler(
            categoryRepository: $repository,
            categoryList: $categoryList,
            searchCriteriaBuilder: $searchCriteriaBuilder,
        );

        $handler->clean();
    }

    private function createHandler(
        ?CategoryInterfaceFactory $categoryFactory = null,
        ?CategoryRepositoryInterface $categoryRepository = null,
        ?CategoryListInterface $categoryList = null,
        ?SearchCriteriaBuilder $searchCriteriaBuilder = null,
    ): CategoryHandler {
        return new CategoryHandler(
            $categoryFactory ?? $this->createMock(CategoryInterfaceFactory::class),
            $categoryRepository ?? $this->createMock(CategoryRepositoryInterface::class),
            $categoryList ?? $this->createMock(CategoryListInterface::class),
            $searchCriteriaBuilder ?? $this->createMock(SearchCriteriaBuilder::class),
        );
    }
}
