<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler\Product\TypeBuilder;

use Magento\Catalog\Model\Product;
use Magento\Downloadable\Api\Data\LinkInterface;
use Magento\Downloadable\Api\Data\LinkInterfaceFactory;
use Magento\Downloadable\Api\LinkRepositoryInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RunAsRoot\Seeder\EntityHandler\Product\TypeBuilder\DownloadableBuilder;

final class DownloadableBuilderTest extends TestCase
{
    private LinkInterfaceFactory&MockObject $linkFactory;
    private LinkRepositoryInterface&MockObject $linkRepository;
    private DirectoryList&MockObject $directoryList;
    private LoggerInterface&MockObject $logger;
    private string $tmpMediaRoot;

    protected function setUp(): void
    {
        $this->linkFactory = $this->createMock(LinkInterfaceFactory::class);
        $this->linkRepository = $this->createMock(LinkRepositoryInterface::class);
        $this->directoryList = $this->createMock(DirectoryList::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->tmpMediaRoot = sys_get_temp_dir() . '/seeder-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmpMediaRoot, 0775, true);

        $this->directoryList->method('getPath')->willReturn($this->tmpMediaRoot);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmpMediaRoot);
    }

    public function test_get_type_returns_downloadable(): void
    {
        $this->assertSame('downloadable', $this->newBuilder()->getType());
    }

    public function test_build_sets_type_id_and_links_config(): void
    {
        $product = $this->createMock(Product::class);
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

    public function test_after_save_creates_files_and_saves_one_link_per_spec(): void
    {
        $parent = $this->createMock(Product::class);
        $parent->method('getSku')->willReturn('SEED-DL-001');

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

        $filesDir = $this->tmpMediaRoot . '/downloadable/files';
        $samplesDir = $this->tmpMediaRoot . '/downloadable/files_sample';

        $this->assertDirectoryExists($filesDir);
        $this->assertDirectoryExists($samplesDir);

        $files = $this->listFiles($filesDir);
        $samples = $this->listFiles($samplesDir);

        $this->assertCount(2, $files);
        $this->assertCount(2, $samples);
    }

    public function test_after_save_writes_full_content_to_link_file_and_first_100_chars_to_sample(): void
    {
        $parent = $this->createMock(Product::class);
        $parent->method('getSku')->willReturn('SEED-DL-002');

        $this->linkFactory->method('create')->willReturnCallback(fn () => $this->stubLink());
        $this->linkRepository->method('save')->willReturn(1);

        $longText = str_repeat('A', 200);

        $data = [
            'downloadable' => [
                'links' => [
                    ['title' => 'Long', 'sample_text' => $longText],
                ],
            ],
        ];

        $this->newBuilder()->afterSave($parent, $data);

        $filesDir = $this->tmpMediaRoot . '/downloadable/files';
        $samplesDir = $this->tmpMediaRoot . '/downloadable/files_sample';

        $linkFiles = $this->listFiles($filesDir);
        $sampleFiles = $this->listFiles($samplesDir);

        $this->assertCount(1, $linkFiles);
        $this->assertCount(1, $sampleFiles);

        $linkContent = file_get_contents($filesDir . '/' . $linkFiles[0]);
        $sampleContent = file_get_contents($samplesDir . '/' . $sampleFiles[0]);

        $this->assertSame($longText, $linkContent);
        $this->assertSame(100, strlen($sampleContent));
        $this->assertSame(substr($longText, 0, 100), $sampleContent);
    }

    public function test_after_save_creates_missing_directories(): void
    {
        $parent = $this->createMock(Product::class);
        $parent->method('getSku')->willReturn('SEED-DL-003');

        $this->linkFactory->method('create')->willReturnCallback(fn () => $this->stubLink());
        $this->linkRepository->method('save')->willReturn(1);

        $filesDir = $this->tmpMediaRoot . '/downloadable/files';
        $samplesDir = $this->tmpMediaRoot . '/downloadable/files_sample';

        $this->assertDirectoryDoesNotExist($filesDir);
        $this->assertDirectoryDoesNotExist($samplesDir);

        $data = [
            'downloadable' => [
                'links' => [
                    ['title' => 'X', 'sample_text' => 'text'],
                ],
            ],
        ];

        $this->newBuilder()->afterSave($parent, $data);

        $this->assertDirectoryExists($filesDir);
        $this->assertDirectoryExists($samplesDir);
    }

    public function test_after_save_skips_links_section_when_data_missing(): void
    {
        $parent = $this->createMock(Product::class);
        $parent->method('getSku')->willReturn('SEED-DL-004');

        $this->linkFactory->expects($this->never())->method('create');
        $this->linkRepository->expects($this->never())->method('save');

        $this->newBuilder()->afterSave($parent, []);

        $this->assertDirectoryDoesNotExist($this->tmpMediaRoot . '/downloadable/files');
        $this->assertDirectoryDoesNotExist($this->tmpMediaRoot . '/downloadable/files_sample');
    }

    public function test_after_save_logs_warning_on_link_save_failure_but_continues(): void
    {
        $parent = $this->createMock(Product::class);
        $parent->method('getSku')->willReturn('SEED-DL-005');

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
            $this->directoryList,
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
        $link->method('setLinkFile')->willReturnSelf();
        $link->method('setSampleType')->willReturnSelf();
        $link->method('setSampleFile')->willReturnSelf();

        return $link;
    }

    /**
     * @return list<string>
     */
    private function listFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $entries = scandir($dir);
        if ($entries === false) {
            return [];
        }

        return array_values(array_filter(
            $entries,
            static fn (string $name) => $name !== '.' && $name !== '..'
        ));
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }
}
