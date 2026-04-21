<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Faker\Provider\Data\Commerce;

use RunAsRoot\Seeder\Faker\Provider\CommerceLocaleInterface;

/**
 * Commerce wordlists ported verbatim from @faker-js/faker (MIT).
 * See NOTICE at repo root for attribution + upstream commit hash.
 *
 * Refresh instructions: src/Faker/Provider/Data/Commerce/README.md
 */
final class EnUs implements CommerceLocaleInterface
{
    /** @return non-empty-list<string> */
    public function adjectives(): array
    {
        return self::ADJECTIVES;
    }

    /** @return non-empty-list<string> */
    public function materials(): array
    {
        return self::MATERIALS;
    }

    /** @return non-empty-list<string> */
    public function products(): array
    {
        return self::PRODUCTS;
    }

    /** @return non-empty-list<string> */
    public function departments(): array
    {
        return self::DEPARTMENTS;
    }

    /** @var list<string> */
    private const ADJECTIVES = [
        'Awesome', 'Bespoke', 'Electronic', 'Elegant', 'Ergonomic', 'Fantastic',
        'Fresh', 'Frozen', 'Generic', 'Gorgeous', 'Handcrafted', 'Handmade',
        'Incredible', 'Intelligent', 'Licensed', 'Luxurious', 'Modern', 'Oriental',
        'Practical', 'Recycled', 'Refined', 'Rustic', 'Sleek', 'Small',
        'Soft', 'Tasty', 'Unbranded',
    ];

    /** @var list<string> */
    private const MATERIALS = [
        'Aluminum', 'Bamboo', 'Bronze', 'Ceramic', 'Concrete', 'Cotton',
        'Gold', 'Granite', 'Marble', 'Metal', 'Plastic', 'Rubber',
        'Silk', 'Steel', 'Wooden',
    ];

    /** @var list<string> */
    private const PRODUCTS = [
        'Bacon', 'Ball', 'Bike', 'Car', 'Chair', 'Cheese',
        'Chicken', 'Chips', 'Computer', 'Fish', 'Gloves', 'Hat',
        'Keyboard', 'Mouse', 'Pants', 'Pizza', 'Salad', 'Sausages',
        'Shirt', 'Shoes', 'Soap', 'Table', 'Towels', 'Tuna',
    ];

    /** @var list<string> */
    private const DEPARTMENTS = [
        'Automotive', 'Baby', 'Beauty', 'Books', 'Clothing', 'Computers',
        'Electronics', 'Games', 'Garden', 'Grocery', 'Health', 'Home',
        'Industrial', 'Jewelry', 'Kids', 'Movies', 'Music', 'Outdoors',
        'Shoes', 'Sports', 'Tools', 'Toys',
    ];
}
