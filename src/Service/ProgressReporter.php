<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use Laravel\Prompts\Progress;

class ProgressReporter
{
    private ?Progress $progress = null;
    private ?string $currentType = null;
    private int $lastDone = 0;

    /**
     * Push-style callback compatible with GenerateRunner's onProgress signature.
     */
    public function report(string $type, int $done, int $total): void
    {
        if ($total < 10) {
            return;
        }

        if ($this->currentType !== $type) {
            $this->finish();
            $this->currentType = $type;
            $this->progress = new Progress(sprintf('Generating %s', $type), $total);
            $this->progress->start();
            $this->lastDone = 0;
        }

        $delta = max(0, $done - $this->lastDone);
        if ($delta > 0) {
            $this->progress?->advance($delta);
            $this->lastDone = $done;
        }

        if ($done >= $total) {
            $this->finish();
        }
    }

    public function finish(): void
    {
        $this->progress?->finish();
        $this->progress = null;
        $this->currentType = null;
        $this->lastDone = 0;
    }

    /**
     * Returns a closure matching GenerateRunner's onProgress callable signature.
     */
    public function asCallable(): callable
    {
        return function (string $type, int $done, int $total): void {
            $this->report($type, $done, $total);
        };
    }
}
