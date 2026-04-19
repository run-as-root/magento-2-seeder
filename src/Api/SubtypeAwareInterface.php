<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Api;

interface SubtypeAwareInterface
{
    /**
     * Force every generation to use the given subtype. Pass null to resume weighted random.
     */
    public function setForcedSubtype(?string $subtype): void;
}
