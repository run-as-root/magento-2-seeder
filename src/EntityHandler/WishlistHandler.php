<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Wishlist\Model\WishlistFactory;
use RunAsRoot\Seeder\Api\EntityHandlerInterface;

class WishlistHandler implements EntityHandlerInterface
{
    public function __construct(
        private readonly WishlistFactory $wishlistFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ResourceConnection $resource,
    ) {
    }

    public function getType(): string
    {
        return 'wishlist';
    }

    public function create(array $data): int
    {
        $wishlist = $this->wishlistFactory->create();
        $wishlist->loadByCustomerId((int) $data['customer_id'], true);
        $wishlist->setShared((int) ($data['shared'] ?? 0));
        $wishlist->save();

        $wishlistId = (int) $wishlist->getId();

        // Insert items directly at the resource level. Wishlist::addNewItem calls
        // Product::getIsSalable() which can reject freshly seeded products whose
        // stock status index has not caught up — a non-issue for seed data. The
        // direct insert mirrors what addNewItem stores on a happy path but is
        // indexer-agnostic, so the seeder stays resilient to setup ordering.
        $connection = $this->resource->getConnection();
        $itemTable = $this->resource->getTableName('wishlist_item');
        foreach ($data['items'] as $itemData) {
            $product = $this->productRepository->getById((int) $itemData['product_id']);
            $connection->insert($itemTable, [
                'wishlist_id' => $wishlistId,
                'product_id' => (int) $product->getId(),
                'store_id' => (int) ($itemData['store_id'] ?? $product->getStoreId() ?? 1),
                'added_at' => date('Y-m-d H:i:s'),
                'description' => null,
                'qty' => (float) ($itemData['qty'] ?? 1),
            ]);
        }

        return $wishlistId;
    }

    public function clean(): void
    {
        $connection = $this->resource->getConnection();
        $wishlistTable = $this->resource->getTableName('wishlist');
        $customerTable = $this->resource->getTableName('customer_entity');

        $select = $connection->select()
            ->from($customerTable, ['entity_id'])
            ->where('email LIKE ?', '%@example.%');
        $customerIds = $connection->fetchCol($select);

        if (!empty($customerIds)) {
            $connection->delete($wishlistTable, ['customer_id IN (?)' => $customerIds]);
        }
    }
}
