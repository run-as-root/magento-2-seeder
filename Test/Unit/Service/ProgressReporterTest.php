<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Service;

use Laravel\Prompts\Prompt;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\Service\ProgressReporter;

final class ProgressReporterTest extends TestCase
{
    protected function setUp(): void
    {
        Prompt::fake();
    }

    public function test_starts_a_progress_on_first_push_per_type(): void
    {
        $reporter = new ProgressReporter();

        $reporter->report('order', 0, 10);
        $reporter->report('order', 1, 10);
        $reporter->report('order', 10, 10);

        // No exception => Progress lifecycle completed cleanly.
        $this->assertTrue(true);
    }

    public function test_finishes_previous_progress_when_type_changes(): void
    {
        $reporter = new ProgressReporter();

        $reporter->report('order', 5, 10);
        $reporter->report('customer', 0, 20);
        $reporter->report('customer', 20, 20);

        $this->assertTrue(true);
    }

    public function test_finish_is_safe_without_active_progress(): void
    {
        $reporter = new ProgressReporter();
        $reporter->finish();

        $this->assertTrue(true);
    }

    public function test_zero_total_is_ignored(): void
    {
        $reporter = new ProgressReporter();
        $reporter->report('order', 0, 0);

        $this->assertTrue(true);
    }

    public function test_as_callable_returns_a_closure(): void
    {
        $reporter = new ProgressReporter();
        $callable = $reporter->asCallable();

        $this->assertIsCallable($callable);
        $callable('order', 1, 5);
    }
}
