<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Faker\Provider;

use RunAsRoot\Seeder\Faker\Provider\Data\Commerce\EnUs;
use Faker\Generator;

final class CommerceProviderFactory
{
    /** @var array<string, class-string<CommerceLocaleInterface>> */
    private const LOCALE_MAP = [
        'en_US' => EnUs::class,
    ];

    public function create(string $locale, Generator $generator): CommerceProvider
    {
        $localeClass = self::LOCALE_MAP[$locale] ?? EnUs::class;

        return new CommerceProvider($generator, new $localeClass());
    }
}
