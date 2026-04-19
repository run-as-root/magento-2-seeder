<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler\Product\TypeBuilder;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Downloadable\Api\Data\File\ContentInterfaceFactory;
use Magento\Downloadable\Api\Data\LinkInterfaceFactory;
use Magento\Downloadable\Api\LinkRepositoryInterface;
use Psr\Log\LoggerInterface;
use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilderInterface;

class DownloadableBuilder implements TypeBuilderInterface
{
    private const TYPE_DOWNLOADABLE = 'downloadable';
    private const SAMPLE_LENGTH = 100;

    public function __construct(
        private readonly LinkInterfaceFactory $linkFactory,
        private readonly LinkRepositoryInterface $linkRepository,
        private readonly ContentInterfaceFactory $fileContentFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getType(): string
    {
        return self::TYPE_DOWNLOADABLE;
    }

    public function build(ProductInterface $product, array $data): void
    {
        $product->setTypeId(self::TYPE_DOWNLOADABLE);
        $product->setData('links_purchased_separately', 1);
        $product->setData('links_title', 'Downloads');
    }

    public function afterSave(ProductInterface $parent, array $data): void
    {
        $links = $data['downloadable']['links'] ?? null;
        if (!is_array($links) || $links === []) {
            return;
        }

        $parentSku = (string) $parent->getSku();

        foreach (array_values($links) as $index => $spec) {
            try {
                $this->createAndSaveLink($parentSku, $index, $spec);
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    'DownloadableBuilder: failed to create link for SKU "%s": %s',
                    $parentSku,
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * @param array{title?: string, sample_text?: string} $spec
     */
    private function createAndSaveLink(string $parentSku, int $index, array $spec): void
    {
        $title = (string) ($spec['title'] ?? 'Download');
        $sampleText = (string) ($spec['sample_text'] ?? '');

        $filename = 'seed-' . bin2hex(random_bytes(6)) . '.txt';
        $sampleName = str_replace('.txt', '-sample.txt', $filename);

        $linkContent = $this->fileContentFactory->create();
        $linkContent->setFileData(base64_encode($sampleText));
        $linkContent->setName($filename);

        $sampleContent = $this->fileContentFactory->create();
        $sampleContent->setFileData(base64_encode(substr($sampleText, 0, self::SAMPLE_LENGTH)));
        $sampleContent->setName($sampleName);

        $link = $this->linkFactory->create();
        $link->setTitle($title);
        $link->setPrice(0.0);
        $link->setIsShareable(0);
        $link->setNumberOfDownloads(0);
        $link->setSortOrder($index);
        $link->setLinkType('file');
        $link->setSampleType('file');
        $link->setLinkFileContent($linkContent);
        $link->setSampleFileContent($sampleContent);

        $this->linkRepository->save($parentSku, $link);
    }
}
