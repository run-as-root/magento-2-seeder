<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler\Product\TypeBuilder;

use Magento\Catalog\Model\Product;
use Magento\Downloadable\Api\Data\File\ContentInterface;
use Magento\Downloadable\Api\Data\File\ContentInterfaceFactory;
use Magento\Downloadable\Api\Data\LinkInterface;
use Magento\Downloadable\Api\Data\LinkInterfaceFactory;
use Magento\Downloadable\Api\LinkRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilder\DownloadableBuilder;

final class DownloadableBuilderTest extends TestCase
{
    private LinkInterfaceFactory&MockObject $linkFactory;
    private LinkRepositoryInterface&MockObject $linkRepository;
    private ContentInterfaceFactory&MockObject $fileContentFactory;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->linkFactory = $this->createMock(LinkInterfaceFactory::class);
        $this->linkRepository = $this->createMock(LinkRepositoryInterface::class);
        $this->fileContentFactory = $this->createMock(ContentInterfaceFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function test_get_type_returns_downloadable(): void
    {
        $this->assertSame('downloadable', $this->newBuilder()->getType());
    }

    public function test_build_sets_type_id_and_links_config(): void
    {
        $product = $this->createProductMock();
        $product->expects($this->once())->method('setTypeId')->with('downloadable');

        $setDataCalls = [];
        $product->method('setData')->willReturnCallback(
            function ($key, $value) use ($product, &$setDataCalls) {
                $setDataCalls[$key] = $value;

                return $product;
            }
        );

        $this->newBuilder()->build($product, []);

        $this->assertSame(1, $setDataCalls['links_purchased_separately'] ?? null);
        $this->assertSame('Downloads', $setDataCalls['links_title'] ?? null);
    }

    public function test_after_save_saves_one_link_per_spec(): void
    {
        $parent = $this->createProductMock();
        $parent->method('getSku')->willReturn('SEED-DL-001');

        $this->fileContentFactory->method('create')->willReturnCallback(fn () => $this->stubContent());
        $this->linkFactory->method('create')->willReturnCallback(fn () => $this->stubLink());

        $this->linkRepository->expects($this->exactly(2))
            ->method('save')
            ->with('SEED-DL-001');

        $data = [
            'downloadable' => [
                'links' => [
                    ['title' => 'Link A', 'sample_text' => 'Hello world, sample A'],
                    ['title' => 'Link B', 'sample_text' => 'Hello world, sample B'],
                ],
            ],
        ];

        $this->newBuilder()->afterSave($parent, $data);
    }

    public function test_after_save_sets_link_file_content_with_base64_of_sample_text(): void
    {
        $parent = $this->createProductMock();
        $parent->method('getSku')->willReturn('SEED-DL-002');

        $sampleText = str_repeat('A', 200);

        $fileDataCalls = [];
        $this->fileContentFactory->method('create')->willReturnCallback(
            function () use (&$fileDataCalls) {
                $content = $this->createMock(ContentInterface::class);
                $content->method('setFileData')->willReturnCallback(
                    function (string $data) use ($content, &$fileDataCalls) {
                        $fileDataCalls[] = $data;

                        return $content;
                    }
                );
                $content->method('setName')->willReturnSelf();

                return $content;
            }
        );

        $this->linkFactory->method('create')->willReturnCallback(fn () => $this->stubLink());
        $this->linkRepository->method('save')->willReturn(1);

        $data = [
            'downloadable' => [
                'links' => [
                    ['title' => 'Long', 'sample_text' => $sampleText],
                ],
            ],
        ];

        $this->newBuilder()->afterSave($parent, $data);

        $this->assertCount(2, $fileDataCalls);
        $this->assertSame(base64_encode($sampleText), $fileDataCalls[0]);
        $this->assertSame(base64_encode(substr($sampleText, 0, 100)), $fileDataCalls[1]);
    }

    public function test_after_save_sets_link_and_sample_types_to_file(): void
    {
        $parent = $this->createProductMock();
        $parent->method('getSku')->willReturn('SEED-DL-003');

        $this->fileContentFactory->method('create')->willReturnCallback(fn () => $this->stubContent());
        $this->linkRepository->method('save')->willReturn(1);

        $linkTypeCalls = [];
        $sampleTypeCalls = [];

        $this->linkFactory->method('create')->willReturnCallback(
            function () use (&$linkTypeCalls, &$sampleTypeCalls) {
                $link = $this->createMock(LinkInterface::class);
                $link->method('setTitle')->willReturnSelf();
                $link->method('setPrice')->willReturnSelf();
                $link->method('setIsShareable')->willReturnSelf();
                $link->method('setNumberOfDownloads')->willReturnSelf();
                $link->method('setSortOrder')->willReturnSelf();
                $link->method('setLinkType')->willReturnCallback(
                    function (string $type) use ($link, &$linkTypeCalls) {
                        $linkTypeCalls[] = $type;

                        return $link;
                    }
                );
                $link->method('setSampleType')->willReturnCallback(
                    function (string $type) use ($link, &$sampleTypeCalls) {
                        $sampleTypeCalls[] = $type;

                        return $link;
                    }
                );
                $link->method('setLinkFileContent')->willReturnSelf();
                $link->method('setSampleFileContent')->willReturnSelf();

                return $link;
            }
        );

        $data = [
            'downloadable' => [
                'links' => [
                    ['title' => 'A', 'sample_text' => 'aa'],
                    ['title' => 'B', 'sample_text' => 'bb'],
                ],
            ],
        ];

        $this->newBuilder()->afterSave($parent, $data);

        $this->assertSame(['file', 'file'], $linkTypeCalls);
        $this->assertSame(['file', 'file'], $sampleTypeCalls);
    }

    public function test_after_save_skips_links_section_when_data_missing(): void
    {
        $parent = $this->createProductMock();
        $parent->method('getSku')->willReturn('SEED-DL-004');

        $this->linkFactory->expects($this->never())->method('create');
        $this->fileContentFactory->expects($this->never())->method('create');
        $this->linkRepository->expects($this->never())->method('save');

        $this->newBuilder()->afterSave($parent, []);
    }

    public function test_after_save_logs_warning_on_link_save_failure_but_continues(): void
    {
        $parent = $this->createProductMock();
        $parent->method('getSku')->willReturn('SEED-DL-005');

        $this->fileContentFactory->method('create')->willReturnCallback(fn () => $this->stubContent());
        $this->linkFactory->method('create')->willReturnCallback(fn () => $this->stubLink());

        $callCount = 0;
        $this->linkRepository->method('save')->willReturnCallback(
            function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    throw new \RuntimeException('boom');
                }

                return 2;
            }
        );

        $this->logger->expects($this->once())->method('warning');

        $data = [
            'downloadable' => [
                'links' => [
                    ['title' => 'Will fail', 'sample_text' => 'nope'],
                    ['title' => 'Will succeed', 'sample_text' => 'yes'],
                ],
            ],
        ];

        $this->newBuilder()->afterSave($parent, $data);

        $this->assertSame(2, $callCount);
    }

    private function newBuilder(): DownloadableBuilder
    {
        return new DownloadableBuilder(
            $this->linkFactory,
            $this->linkRepository,
            $this->fileContentFactory,
            $this->logger,
        );
    }

    private function stubLink(): LinkInterface
    {
        $link = $this->createMock(LinkInterface::class);
        $link->method('setTitle')->willReturnSelf();
        $link->method('setPrice')->willReturnSelf();
        $link->method('setIsShareable')->willReturnSelf();
        $link->method('setNumberOfDownloads')->willReturnSelf();
        $link->method('setSortOrder')->willReturnSelf();
        $link->method('setLinkType')->willReturnSelf();
        $link->method('setSampleType')->willReturnSelf();
        $link->method('setLinkFileContent')->willReturnSelf();
        $link->method('setSampleFileContent')->willReturnSelf();

        return $link;
    }

    private function stubContent(): ContentInterface
    {
        $content = $this->createMock(ContentInterface::class);
        $content->method('setFileData')->willReturnSelf();
        $content->method('setName')->willReturnSelf();

        return $content;
    }
    private function createProductMock(): Product&MockObject
    {
        return $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getId', 'getSku', 'setSku', 'getName', 'setName',
                'getPrice', 'setPrice', 'getAttributeSetId', 'setAttributeSetId',
                'getStatus', 'setStatus', 'getVisibility', 'setVisibility',
                'getTypeId', 'setTypeId', 'getWeight', 'setWeight',
                'setCustomAttribute', 'setProductLinks',
                'setData', 'getData', 'addImageToMediaGallery',
                'setStockData',
            ])
            ->addMethods(['setWebsiteIds'])
            ->getMock();
    }

}
