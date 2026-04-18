<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

class ImageDownloader
{
    public function download(string $url, string $destinationDir): ?string
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'follow_location' => true,
                ],
            ]);

            $imageData = @file_get_contents($url, false, $context);
            if ($imageData === false) {
                return null;
            }

            $filename = 'seed_' . bin2hex(random_bytes(8)) . '.jpg';
            $filePath = rtrim($destinationDir, '/') . '/' . $filename;

            if (!is_dir($destinationDir)) {
                mkdir($destinationDir, 0777, true);
            }

            file_put_contents($filePath, $imageData);

            return $filename;
        } catch (\Throwable) {
            return null;
        }
    }
}
