<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service;

use RunAsRoot\Seeder\Service\ImageDownloader;
use PHPUnit\Framework\TestCase;

final class ImageDownloaderTest extends TestCase
{
    public function test_returns_null_on_invalid_url(): void
    {
        $downloader = new ImageDownloader();
        $result = $downloader->download('https://invalid.example.com/404.jpg', sys_get_temp_dir());

        $this->assertNull($result);
    }

    public function test_returns_filename_on_success(): void
    {
        if (!@file_get_contents('https://picsum.photos/10/10', false, stream_context_create(['http' => ['timeout' => 3]]))) {
            $this->markTestSkipped('picsum.photos unreachable');
        }

        $downloader = new ImageDownloader();
        $destDir = sys_get_temp_dir() . '/seeder_img_test_' . uniqid();
        mkdir($destDir, 0777, true);

        $filename = $downloader->download('https://picsum.photos/10/10', $destDir);

        $this->assertNotNull($filename);
        $this->assertFileExists($destDir . '/' . $filename);

        unlink($destDir . '/' . $filename);
        rmdir($destDir);
    }
}
