<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Wishlist\Model\Wishlist;
use Magento\Wishlist\Model\WishlistFactory;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\EntityHandler\WishlistHandler;

final class WishlistHandlerTest extends TestCase
{
    public function test_get_type_returns_wishlist(): void
    {
        $handler = $this->createHandler();

        $this->assertSame('wishlist', $handler->getType());
    }

    public function test_create_loads_wishlist_and_inserts_item(): void
    {
        $wishlist = $this->createWishlistMock();
        $wishlist->expects($this->once())
            ->method('loadByCustomerId')
            ->with(42, true)
            ->willReturnSelf();
        $wishlist->expects($this->once())->method('setShared')->with(0)->willReturnSelf();
        $wishlist->expects($this->once())->method('save')->willReturnSelf();
        $wishlist->method('getId')->willReturn(7);

        $wishlistFactory = $this->createMock(WishlistFactory::class);
        $wishlistFactory->method('create')->willReturn($wishlist);

        $product = $this->createProductMock(101, 1);

        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects($this->once())
            ->method('getById')
            ->with(101)
            ->willReturn($product);

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())
            ->method('insert')
            ->willReturnCallback(function (string $table, array $bind): int {
                $this->assertSame('wishlist_item', $table);
                $this->assertSame(7, $bind['wishlist_id']);
                $this->assertSame(101, $bind['product_id']);
                $this->assertSame(1, $bind['store_id']);
                $this->assertMatchesRegularExpression(
                    '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
                    $bind['added_at']
                );
                $this->assertNull($bind['description']);
                $this->assertSame(2.0, $bind['qty']);
                return 1;
            });

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $handler = $this->createHandler(
            wishlistFactory: $wishlistFactory,
            productRepository: $productRepository,
            resource: $resource,
        );

        $handler->create([
            'customer_id' => 42,
            'items' => [
                ['product_id' => 101, 'qty' => 2],
            ],
        ]);
    }

    public function test_create_uses_explicit_store_id_from_item_data(): void
    {
        $product = $this->createProductMock(1, 9); // getStoreId should be overridden

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())
            ->method('insert')
            ->willReturnCallback(function (string $table, array $bind): int {
                $this->assertSame(2, $bind['store_id']);
                return 1;
            });

        $handler = $this->buildInsertingHandler($product, $connection);

        $handler->create([
            'customer_id' => 1,
            'items' => [
                ['product_id' => 1, 'store_id' => 2],
            ],
        ]);
    }

    public function test_create_falls_back_to_product_store_id_when_item_store_id_unset(): void
    {
        $product = $this->createProductMock(1, 3);

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())
            ->method('insert')
            ->willReturnCallback(function (string $table, array $bind): int {
                $this->assertSame(3, $bind['store_id']);
                return 1;
            });

        $handler = $this->buildInsertingHandler($product, $connection);

        $handler->create([
            'customer_id' => 1,
            'items' => [
                ['product_id' => 1],
            ],
        ]);
    }

    public function test_create_falls_back_to_store_id_one_when_product_store_id_is_zero(): void
    {
        $product = $this->createProductMock(1, 0);

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())
            ->method('insert')
            ->willReturnCallback(function (string $table, array $bind): int {
                $this->assertSame(1, $bind['store_id']);
                return 1;
            });

        $handler = $this->buildInsertingHandler($product, $connection);

        $handler->create([
            'customer_id' => 1,
            'items' => [
                ['product_id' => 1],
            ],
        ]);
    }

    public function test_create_inserts_each_item_separately(): void
    {
        $wishlist = $this->createWishlistMock();
        $wishlist->method('loadByCustomerId')->willReturnSelf();
        $wishlist->method('setShared')->willReturnSelf();
        $wishlist->method('save')->willReturnSelf();
        $wishlist->method('getId')->willReturn(1);

        $wishlistFactory = $this->createMock(WishlistFactory::class);
        $wishlistFactory->method('create')->willReturn($wishlist);

        $productA = $this->createProductMock(10, 1);
        $productB = $this->createProductMock(20, 1);

        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->method('getById')
            ->willReturnCallback(function (int $id) use ($productA, $productB): ProductInterface {
                return $id === 10 ? $productA : $productB;
            });

        $productIds = [];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->exactly(2))
            ->method('insert')
            ->willReturnCallback(function (string $table, array $bind) use (&$productIds): int {
                $productIds[] = $bind['product_id'];
                return 1;
            });

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $handler = $this->createHandler(
            wishlistFactory: $wishlistFactory,
            productRepository: $productRepository,
            resource: $resource,
        );

        $handler->create([
            'customer_id' => 99,
            'items' => [
                ['product_id' => 10],
                ['product_id' => 20],
            ],
        ]);

        $this->assertSame([10, 20], $productIds);
    }

    public function test_clean_deletes_wishlists_for_matching_customer_ids(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchCol')->willReturn([11, 22]);

        $deleteCalls = [];
        $connection->expects($this->once())
            ->method('delete')
            ->willReturnCallback(function (string $table, $where) use (&$deleteCalls): int {
                $deleteCalls[] = [$table, $where];
                return 1;
            });

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $handler = $this->createHandler(resource: $resource);
        $handler->clean();

        $this->assertCount(1, $deleteCalls);
        $this->assertSame('wishlist', $deleteCalls[0][0]);
        $this->assertSame(['customer_id IN (?)' => [11, 22]], $deleteCalls[0][1]);
    }

    public function test_clean_does_not_delete_when_no_customer_ids_match(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchCol')->willReturn([]);
        $connection->expects($this->never())->method('delete');

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $handler = $this->createHandler(resource: $resource);
        $handler->clean();
    }

    private function createWishlistMock(): Wishlist
    {
        return $this->getMockBuilder(Wishlist::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['loadByCustomerId', 'save', 'getId'])
            ->addMethods(['setShared'])
            ->getMock();
    }

    private function createProductMock(int $id, int $storeId): ProductInterface
    {
        $product = $this->getMockBuilder(ProductInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getSku', 'setSku', 'getName', 'setName', 'getPrice', 'setPrice', 'getAttributeSetId', 'setAttributeSetId', 'getStatus', 'setStatus', 'getVisibility', 'setVisibility', 'getTypeId', 'setTypeId', 'getWeight', 'setWeight', 'setCustomAttribute', 'setProductLinks'])
            ->addMethods(['getStoreId'])
            ->getMock();
        $product->method('getId')->willReturn($id);
        $product->method('getStoreId')->willReturn($storeId);
        return $product;
    }

    private function buildInsertingHandler(
        ProductInterface $product,
        AdapterInterface $connection,
    ): WishlistHandler {
        $wishlist = $this->createWishlistMock();
        $wishlist->method('loadByCustomerId')->willReturnSelf();
        $wishlist->method('setShared')->willReturnSelf();
        $wishlist->method('save')->willReturnSelf();
        $wishlist->method('getId')->willReturn(1);

        $wishlistFactory = $this->createMock(WishlistFactory::class);
        $wishlistFactory->method('create')->willReturn($wishlist);

        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->method('getById')->willReturn($product);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        return $this->createHandler(
            wishlistFactory: $wishlistFactory,
            productRepository: $productRepository,
            resource: $resource,
        );
    }

    private function createHandler(
        ?WishlistFactory $wishlistFactory = null,
        ?ProductRepositoryInterface $productRepository = null,
        ?ResourceConnection $resource = null,
    ): WishlistHandler {
        return new WishlistHandler(
            $wishlistFactory ?? $this->createMock(WishlistFactory::class),
            $productRepository ?? $this->createMock(ProductRepositoryInterface::class),
            $resource ?? $this->createMock(ResourceConnection::class),
        );
    }
}
