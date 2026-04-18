<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler;

use RunAsRoot\Seeder\Api\EntityHandlerInterface;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterfaceFactory;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class CmsHandler implements EntityHandlerInterface
{
    private const SEED_PREFIX = 'seed-';

    public function __construct(
        private readonly PageInterfaceFactory $pageFactory,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly BlockInterfaceFactory $blockFactory,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
    ) {
    }

    public function getType(): string
    {
        return 'cms';
    }

    public function create(array $data): void
    {
        $cmsType = $data['cms_type'] ?? 'page';

        if ($cmsType === 'block') {
            $this->createBlock($data);
        } else {
            $this->createPage($data);
        }
    }

    public function clean(): void
    {
        $this->cleanPages();
        $this->cleanBlocks();
    }

    private function createPage(array $data): void
    {
        $page = $this->pageFactory->create();
        $page->setIdentifier($data['identifier'])
            ->setTitle($data['title'])
            ->setContent($data['content'] ?? '')
            ->setIsActive($data['is_active'] ?? true)
            ->setStoreId($data['store_id'] ?? [0]);

        $this->pageRepository->save($page);
    }

    private function createBlock(array $data): void
    {
        $block = $this->blockFactory->create();
        $block->setIdentifier($data['identifier'])
            ->setTitle($data['title'])
            ->setContent($data['content'] ?? '')
            ->setIsActive($data['is_active'] ?? true)
            ->setStoreId($data['store_id'] ?? [0]);

        $this->blockRepository->save($block);
    }

    private function cleanPages(): void
    {
        $this->searchCriteriaBuilder->addFilter('identifier', self::SEED_PREFIX . '%', 'like');
        $searchCriteria = $this->searchCriteriaBuilder->setPageSize(10000)->create();
        $pages = $this->pageRepository->getList($searchCriteria);

        foreach ($pages->getItems() as $page) {
            $this->pageRepository->deleteById((int) $page->getId());
        }
    }

    private function cleanBlocks(): void
    {
        $this->searchCriteriaBuilder->addFilter('identifier', self::SEED_PREFIX . '%', 'like');
        $searchCriteria = $this->searchCriteriaBuilder->setPageSize(10000)->create();
        $blocks = $this->blockRepository->getList($searchCriteria);

        foreach ($blocks->getItems() as $block) {
            $this->blockRepository->deleteById((int) $block->getId());
        }
    }
}
