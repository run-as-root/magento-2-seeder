<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler;

use RunAsRoot\Seeder\EntityHandler\CmsHandler;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterface;
use Magento\Cms\Api\Data\BlockInterfaceFactory;
use Magento\Cms\Api\Data\BlockSearchResultsInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\Data\PageSearchResultsInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use PHPUnit\Framework\TestCase;

final class CmsHandlerTest extends TestCase
{
    public function test_get_type_returns_cms(): void
    {
        $handler = $this->createHandler();

        $this->assertSame('cms', $handler->getType());
    }

    public function test_create_saves_cms_page(): void
    {
        $page = $this->createMock(PageInterface::class);
        $page->expects($this->once())->method('setIdentifier')->with('seed-test-page')->willReturnSelf();
        $page->expects($this->once())->method('setTitle')->with('Test Page')->willReturnSelf();
        $page->expects($this->once())->method('setContent')->with('<p>Hello</p>')->willReturnSelf();
        $page->expects($this->once())->method('setIsActive')->with(true)->willReturnSelf();
        $page->method('setStoreId')->willReturnSelf();

        $pageFactory = $this->createMock(PageInterfaceFactory::class);
        $pageFactory->method('create')->willReturn($page);

        $pageRepository = $this->createMock(PageRepositoryInterface::class);
        $pageRepository->expects($this->once())->method('save')->with($page);

        $handler = $this->createHandler(
            pageFactory: $pageFactory,
            pageRepository: $pageRepository,
        );

        $handler->create([
            'cms_type' => 'page',
            'identifier' => 'seed-test-page',
            'title' => 'Test Page',
            'content' => '<p>Hello</p>',
        ]);
    }

    public function test_create_saves_cms_block(): void
    {
        $block = $this->createMock(BlockInterface::class);
        $block->expects($this->once())->method('setIdentifier')->with('seed-test-block')->willReturnSelf();
        $block->expects($this->once())->method('setTitle')->with('Test Block')->willReturnSelf();
        $block->expects($this->once())->method('setContent')->with('<p>Block</p>')->willReturnSelf();
        $block->expects($this->once())->method('setIsActive')->with(true)->willReturnSelf();
        $block->method('setStoreId')->willReturnSelf();

        $blockFactory = $this->createMock(BlockInterfaceFactory::class);
        $blockFactory->method('create')->willReturn($block);

        $blockRepository = $this->createMock(BlockRepositoryInterface::class);
        $blockRepository->expects($this->once())->method('save')->with($block);

        $handler = $this->createHandler(
            blockFactory: $blockFactory,
            blockRepository: $blockRepository,
        );

        $handler->create([
            'cms_type' => 'block',
            'identifier' => 'seed-test-block',
            'title' => 'Test Block',
            'content' => '<p>Block</p>',
        ]);
    }

    public function test_clean_deletes_only_seed_prefixed_pages_and_blocks(): void
    {
        $page = $this->createMock(PageInterface::class);
        $page->method('getId')->willReturn('10');

        $pageSearchResults = $this->createMock(PageSearchResultsInterface::class);
        $pageSearchResults->method('getItems')->willReturn([$page]);

        $block = $this->createMock(BlockInterface::class);
        $block->method('getId')->willReturn('20');

        $blockSearchResults = $this->createMock(BlockSearchResultsInterface::class);
        $blockSearchResults->method('getItems')->willReturn([$block]);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);

        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $searchCriteriaBuilder->method('setPageSize')->willReturnSelf();
        $searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $pageRepository = $this->createMock(PageRepositoryInterface::class);
        $pageRepository->method('getList')->willReturn($pageSearchResults);
        $pageRepository->expects($this->once())->method('deleteById')->with(10);

        $blockRepository = $this->createMock(BlockRepositoryInterface::class);
        $blockRepository->method('getList')->willReturn($blockSearchResults);
        $blockRepository->expects($this->once())->method('deleteById')->with(20);

        $handler = $this->createHandler(
            pageRepository: $pageRepository,
            blockRepository: $blockRepository,
            searchCriteriaBuilder: $searchCriteriaBuilder,
        );

        $handler->clean();
    }

    private function createHandler(
        ?PageInterfaceFactory $pageFactory = null,
        ?PageRepositoryInterface $pageRepository = null,
        ?BlockInterfaceFactory $blockFactory = null,
        ?BlockRepositoryInterface $blockRepository = null,
        ?SearchCriteriaBuilder $searchCriteriaBuilder = null,
    ): CmsHandler {
        return new CmsHandler(
            $pageFactory ?? $this->createMock(PageInterfaceFactory::class),
            $pageRepository ?? $this->createMock(PageRepositoryInterface::class),
            $blockFactory ?? $this->createMock(BlockInterfaceFactory::class),
            $blockRepository ?? $this->createMock(BlockRepositoryInterface::class),
            $searchCriteriaBuilder ?? $this->createMock(SearchCriteriaBuilder::class),
        );
    }
}
