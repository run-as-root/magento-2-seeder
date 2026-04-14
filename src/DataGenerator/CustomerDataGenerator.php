<?php

declare(strict_types=1);

namespace DavidLambauer\Seeder\DataGenerator;

use DavidLambauer\Seeder\Api\DataGeneratorInterface;
use DavidLambauer\Seeder\Service\GeneratedDataRegistry;
use Faker\Generator;

class CustomerDataGenerator implements DataGeneratorInterface
{
    public function getType(): string
    {
        return 'customer';
    }

    public function getOrder(): int
    {
        return 30;
    }

    public function generate(Generator $faker, GeneratedDataRegistry $registry): array
    {
        $firstname = $faker->firstName();
        $lastname = $faker->lastName();

        $address = [
            'firstname' => $firstname,
            'lastname' => $lastname,
            'street' => [$faker->streetAddress()],
            'city' => $faker->city(),
            'region_id' => $faker->numberBetween(1, 65),
            'postcode' => $faker->postcode(),
            'country_id' => 'US',
            'telephone' => $faker->phoneNumber(),
            'default_billing' => true,
            'default_shipping' => true,
        ];

        return [
            'email' => $faker->unique()->safeEmail(),
            'firstname' => $firstname,
            'lastname' => $lastname,
            'password' => 'Test1234!',
            'dob' => $faker->date('Y-m-d', '-18 years'),
            'addresses' => [$address],
        ];
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getDependencyCount(string $dependencyType, int $selfCount): int
    {
        return 0;
    }
}
