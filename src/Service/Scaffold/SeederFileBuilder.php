<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service\Scaffold;

use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

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
            self::FORMAT_PHP  => $this->buildPhp($type, $count, $locale, $seed),
            self::FORMAT_JSON => $this->buildJson($type, $count, $locale, $seed),
            self::FORMAT_YAML => $this->buildYaml($type, $count, $locale, $seed),
        };
    }

    private function buildJson(string $type, int $count, string $locale, ?int $seed): string
    {
        $payload = ['type' => $type, 'count' => $count, 'locale' => $locale];
        if ($seed !== null) {
            $payload['seed'] = $seed;
        }

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function buildYaml(string $type, int $count, string $locale, ?int $seed): string
    {
        $payload = ['type' => $type, 'count' => $count, 'locale' => $locale];
        if ($seed !== null) {
            $payload['seed'] = $seed;
        }

        return Yaml::dump($payload);
    }

    private function buildPhp(string $type, int $count, string $locale, ?int $seed): string
    {
        $typeLiteral = var_export($type, true);
        $localeLiteral = var_export($locale, true);
        $seedLine = $seed !== null
            ? sprintf("    'seed' => %d,\n", $seed)
            : "    // 'seed' is optional: uncomment and set an integer for deterministic output\n";

        return <<<PHP
<?php

declare(strict_types=1);

return [
    'type' => {$typeLiteral},
    'count' => {$count},
    'locale' => {$localeLiteral},
{$seedLine}];

PHP;
    }
}
