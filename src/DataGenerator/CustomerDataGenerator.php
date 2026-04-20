<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\DataGenerator;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
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

        $addressCount = $faker->numberBetween(1, 3);
        $addresses = [];
        for ($i = 0; $i < $addressCount; $i++) {
            $addresses[] = [
                'firstname' => $firstname,
                'lastname' => $lastname,
                'street' => [$faker->streetAddress()],
                'city' => $faker->city(),
                'region_id' => $faker->numberBetween(1, 65),
                'postcode' => $faker->postcode(),
                'country_id' => 'US',
                'telephone' => $this->sanitizeTelephone($faker->phoneNumber()),
                'default_billing' => $i === 0,
                'default_shipping' => $i === 0,
            ];
        }

        return [
            'email' => $faker->unique()->safeEmail(),
            'firstname' => $firstname,
            'lastname' => $lastname,
            'password' => 'Test1234!',
            'dob' => $faker->date('Y-m-d', '-18 years'),
            'addresses' => $addresses,
        ];
    }

    public function getDependencies(): array
    {
        return [];
    }

    private function sanitizeTelephone(string $raw): string
    {
        $cleaned = preg_replace('/[^0-9\-\(\) \+]/', '', $raw) ?? '';
        $cleaned = trim($cleaned);

        return $cleaned !== '' ? $cleaned : '555-0100';
    }

    public function getDependencyCount(string $dependencyType, int $selfCount): int
    {
        return 0;
    }
}
