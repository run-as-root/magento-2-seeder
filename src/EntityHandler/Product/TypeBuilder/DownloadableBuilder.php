<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler\Product\TypeBuilder;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Downloadable\Api\Data\LinkInterfaceFactory;
use Magento\Downloadable\Api\LinkRepositoryInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Psr\Log\LoggerInterface;
use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilderInterface;

class DownloadableBuilder implements TypeBuilderInterface
{
    private const TYPE_DOWNLOADABLE = 'downloadable';
    private const SAMPLE_LENGTH = 100;

    public function __construct(
        private readonly LinkInterfaceFactory $linkFactory,
        private readonly LinkRepositoryInterface $linkRepository,
        private readonly DirectoryList $directoryList,
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

        $mediaRoot = $this->directoryList->getPath(DirectoryList::MEDIA);
        $filesDir = $mediaRoot . '/downloadable/files';
        $samplesDir = $mediaRoot . '/downloadable/files_sample';

        $this->ensureDirectory($filesDir);
        $this->ensureDirectory($samplesDir);

        $parentSku = (string) $parent->getSku();

        foreach (array_values($links) as $index => $spec) {
            try {
                $this->createAndSaveLink($parentSku, $filesDir, $samplesDir, $index, $spec);
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    'DownloadableBuilder: failed to create link for SKU "%s": %s',
                    $parentSku,
                    $e->getMessage()
                ));
            }
        }
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    /**
     * @param array{title?: string, sample_text?: string} $spec
     */
    private function createAndSaveLink(
        string $parentSku,
        string $filesDir,
        string $samplesDir,
        int $index,
        array $spec
    ): void {
        $title = (string) ($spec['title'] ?? 'Download');
        $sampleText = (string) ($spec['sample_text'] ?? '');

        $id = bin2hex(random_bytes(6));
        $linkRelative = "/seed-{$id}.txt";
        $sampleRelative = "/seed-{$id}-sample.txt";

        file_put_contents($filesDir . $linkRelative, $sampleText);
        file_put_contents($samplesDir . $sampleRelative, substr($sampleText, 0, self::SAMPLE_LENGTH));

        $link = $this->linkFactory->create();
        $link->setTitle($title);
        $link->setPrice(0.0);
        $link->setIsShareable(0);
        $link->setNumberOfDownloads(0);
        $link->setSortOrder($index);
        $link->setLinkType('file');
        $link->setLinkFile($linkRelative);
        $link->setSampleType('file');
        $link->setSampleFile($sampleRelative);

        $this->linkRepository->save($parentSku, $link);
    }
}
