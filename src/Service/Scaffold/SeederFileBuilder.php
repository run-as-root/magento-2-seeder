<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service\Scaffold;

use InvalidArgumentException;
use LogicException;

class SeederFileBuilder
{
    public const FORMAT_PHP = 'php';
    public const FORMAT_JSON = 'json';
    public const FORMAT_YAML = 'yaml';

    public const SUPPORTED_FORMATS = [self::FORMAT_PHP, self::FORMAT_JSON, self::FORMAT_YAML];

    public function build(
        string $type,
        int $count,
        string $locale,
        ?int $seed,
        string $format,
    ): string {
        if (!in_array($format, self::SUPPORTED_FORMATS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported format "%s". Use one of: %s',
                $format,
                implode(', ', self::SUPPORTED_FORMATS),
            ));
        }

        return match ($format) {
            self::FORMAT_PHP => $this->buildPhp($type, $count, $locale, $seed),
            default => throw new LogicException(sprintf('Format "%s" is listed as supported but has no builder.', $format)),
        };
    }

    private function buildPhp(string $type, int $count, string $locale, ?int $seed): string
    {
        $seedLine = $seed !== null
            ? sprintf("    'seed' => %d,\n", $seed)
            : "    // 'seed' is optional: uncomment and set an integer for deterministic output\n";

        return <<<PHP
<?php

declare(strict_types=1);

return [
    'type' => '{$type}',
    'count' => {$count},
    'locale' => '{$locale}',
{$seedLine}];

PHP;
    }
}
