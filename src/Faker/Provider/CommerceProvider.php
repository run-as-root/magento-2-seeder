<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Faker\Provider;

use Faker\Generator;
use Faker\Provider\Base;

final class CommerceProvider extends Base
{
    public function __construct(Generator $generator, private readonly CommerceLocaleInterface $locale)
    {
        parent::__construct($generator);
    }

    public function productAdjective(): string
    {
        return static::randomElement($this->locale->adjectives());
    }

    public function productMaterial(): string
    {
        return static::randomElement($this->locale->materials());
    }

    public function product(): string
    {
        return static::randomElement($this->locale->products());
    }

    public function productDepartment(): string
    {
        return static::randomElement($this->locale->departments());
    }

    public function productName(): string
    {
        return $this->productAdjective() . ' ' . $this->productMaterial() . ' ' . $this->product();
    }
}
