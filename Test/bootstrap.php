<?php

declare(strict_types=1);

/**
 * Stub Magento framework classes so unit tests can run outside a Magento installation.
 */
if (!class_exists(\Magento\Framework\Component\ComponentRegistrar::class)) {
    eval('
        namespace Magento\Framework\Component;
        class ComponentRegistrar {
            public const MODULE = "module";
            public static function register(string $type, string $name, string $path): void {}
        }
    ');
}

if (!interface_exists(\Magento\Framework\ObjectManagerInterface::class)) {
    eval('
        namespace Magento\Framework;
        interface ObjectManagerInterface {
            public function create(string $type, array $arguments = []): object;
            public function get(string $type): object;
            public function configure(array $configuration): void;
        }
    ');
}

if (!class_exists(\Magento\Framework\App\Filesystem\DirectoryList::class)) {
    eval('
        namespace Magento\Framework\App\Filesystem;
        class DirectoryList {
            public const MEDIA = "media";
            public function getRoot(): string { return ""; }
            public function getPath(string $code): string { return ""; }
        }
    ');
}

if (!class_exists(\Magento\CatalogInventory\Model\Indexer\Stock\Processor::class)) {
    eval('
        namespace Magento\CatalogInventory\Model\Indexer\Stock;
        class Processor {
            public function reindexRow($productId, $forceReindex = false): void {}
            public function reindexList(array $productIds, $forceReindex = false): void {}
        }
    ');
}

if (!interface_exists(\Psr\Log\LoggerInterface::class)) {
    eval('
        namespace Psr\Log;
        interface LoggerInterface {
            public function emergency(string|\Stringable $message, array $context = []): void;
            public function alert(string|\Stringable $message, array $context = []): void;
            public function critical(string|\Stringable $message, array $context = []): void;
            public function error(string|\Stringable $message, array $context = []): void;
            public function warning(string|\Stringable $message, array $context = []): void;
            public function notice(string|\Stringable $message, array $context = []): void;
            public function info(string|\Stringable $message, array $context = []): void;
            public function debug(string|\Stringable $message, array $context = []): void;
            public function log($level, string|\Stringable $message, array $context = []): void;
        }
    ');
}

if (!class_exists(\Magento\Framework\App\State::class)) {
    eval('
        namespace Magento\Framework\App;
        class State {
            public function setAreaCode(string $code): void {}
            public function getAreaCode(): string { return ""; }
        }
    ');
}

if (!class_exists(\Magento\Framework\App\Area::class)) {
    eval('
        namespace Magento\Framework\App;
        class Area {
            public const AREA_ADMINHTML = "adminhtml";
            public const AREA_FRONTEND = "frontend";
            public const AREA_GLOBAL = "global";
        }
    ');
}

if (!class_exists(\Magento\Framework\Exception\LocalizedException::class)) {
    eval('
        namespace Magento\Framework\Exception;
        class LocalizedException extends \Exception {}
    ');
}

if (!interface_exists(\Magento\Framework\Api\SearchCriteriaInterface::class)) {
    eval('
        namespace Magento\Framework\Api;
        interface SearchCriteriaInterface {
            public function getFilterGroups(): array;
            public function setFilterGroups(array $filterGroups): self;
            public function getSortOrders(): ?array;
            public function setSortOrders(array $sortOrders): self;
            public function getPageSize(): ?int;
            public function setPageSize(int $pageSize): self;
            public function getCurrentPage(): ?int;
            public function setCurrentPage(int $currentPage): self;
        }
    ');
}

if (!class_exists(\Magento\Framework\Api\SearchCriteriaBuilder::class)) {
    eval('
        namespace Magento\Framework\Api;
        class SearchCriteriaBuilder {
            public function addFilter(string $field, $value, ?string $conditionType = null): self { return $this; }
            public function addFilters(array $filters): self { return $this; }
            public function addSortOrder($sortOrder): self { return $this; }
            public function setPageSize(int $pageSize): self { return $this; }
            public function setCurrentPage(int $currentPage): self { return $this; }
            public function create(): SearchCriteriaInterface { return new class implements SearchCriteriaInterface {
                public function getFilterGroups(): array { return []; }
                public function setFilterGroups(array $filterGroups): self { return $this; }
                public function getSortOrders(): ?array { return null; }
                public function setSortOrders(array $sortOrders): self { return $this; }
                public function getPageSize(): ?int { return null; }
                public function setPageSize(int $pageSize): self { return $this; }
                public function getCurrentPage(): ?int { return null; }
                public function setCurrentPage(int $currentPage): self { return $this; }
            }; }
        }
    ');
}

if (!interface_exists(\Magento\Framework\Api\CustomAttributeInterface::class)) {
    eval('
        namespace Magento\Framework\Api;
        interface CustomAttributeInterface {
            public function getAttributeCode(): string;
            public function getValue(): mixed;
        }
    ');
}

if (!interface_exists(\Magento\Catalog\Api\Data\CategoryInterface::class)) {
    eval('
        namespace Magento\Catalog\Api\Data;
        interface CategoryInterface {
            public function getId();
            public function setName(string $name): self;
            public function setIsActive(bool $isActive): self;
            public function setParentId(int $parentId): self;
            public function setCustomAttribute(string $attributeCode, $value): self;
        }
    ');
}

if (!class_exists(\Magento\Catalog\Api\Data\CategoryInterfaceFactory::class)) {
    eval('
        namespace Magento\Catalog\Api\Data;
        class CategoryInterfaceFactory {
            public function create(array $data = []): CategoryInterface {
                return new class implements CategoryInterface {
                    public function getId() { return null; }
                    public function setName(string $name): self { return $this; }
                    public function setIsActive(bool $isActive): self { return $this; }
                    public function setParentId(int $parentId): self { return $this; }
                    public function setCustomAttribute(string $attributeCode, $value): self { return $this; }
                };
            }
        }
    ');
}

if (!interface_exists(\Magento\Catalog\Api\CategoryRepositoryInterface::class)) {
    eval('
        namespace Magento\Catalog\Api;
        interface CategoryRepositoryInterface {
            public function save(\Magento\Catalog\Api\Data\CategoryInterface $category): \Magento\Catalog\Api\Data\CategoryInterface;
            public function get(int $categoryId, ?int $storeId = null): \Magento\Catalog\Api\Data\CategoryInterface;
            public function delete(\Magento\Catalog\Api\Data\CategoryInterface $category): bool;
            public function deleteByIdentifier(int $categoryId): bool;
        }
    ');
}

if (!interface_exists(\Magento\Framework\Api\SearchResultsInterface::class)) {
    eval('
        namespace Magento\Framework\Api;
        interface SearchResultsInterface {
            public function getItems(): array;
            public function setItems(array $items): self;
            public function getSearchCriteria(): SearchCriteriaInterface;
            public function setSearchCriteria(SearchCriteriaInterface $searchCriteria): self;
            public function getTotalCount(): int;
            public function setTotalCount(int $totalCount): self;
        }
    ');
}

if (!interface_exists(\Magento\Catalog\Api\Data\CategorySearchResultsInterface::class)) {
    eval('
        namespace Magento\Catalog\Api\Data;
        interface CategorySearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface {
            public function getItems(): array;
            public function setItems(array $items): \Magento\Framework\Api\SearchResultsInterface;
        }
    ');
}

if (!interface_exists(\Magento\Catalog\Api\CategoryListInterface::class)) {
    eval('
        namespace Magento\Catalog\Api;
        interface CategoryListInterface {
            public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria): \Magento\Catalog\Api\Data\CategorySearchResultsInterface;
        }
    ');
}

if (!interface_exists(\Magento\Customer\Api\Data\CustomerInterface::class)) {
    eval('
        namespace Magento\Customer\Api\Data;
        interface CustomerInterface {
            public function getId();
            public function setId($id);
            public function getEmail(): ?string;
            public function setEmail(string $email);
            public function getFirstname(): ?string;
            public function setFirstname(string $firstname);
            public function getLastname(): ?string;
            public function setLastname(string $lastname);
            public function getWebsiteId(): ?int;
            public function setWebsiteId(int $websiteId);
            public function getStoreId(): ?int;
            public function setStoreId(int $storeId);
            public function getGroupId(): ?int;
            public function setGroupId(int $groupId);
            public function getDob(): ?string;
            public function setDob(string $dob);
        }
    ');
}

if (!class_exists(\Magento\Customer\Api\Data\CustomerInterfaceFactory::class)) {
    eval('
        namespace Magento\Customer\Api\Data;
        class CustomerInterfaceFactory {
            public function create(array $data = []): CustomerInterface {
                throw new \RuntimeException("Stub");
            }
        }
    ');
}

if (!interface_exists(\Magento\Customer\Api\Data\CustomerSearchResultsInterface::class)) {
    eval('
        namespace Magento\Customer\Api\Data;
        interface CustomerSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface {
            public function getItems(): array;
            public function setItems(array $items): \Magento\Framework\Api\SearchResultsInterface;
        }
    ');
}

if (!interface_exists(\Magento\Customer\Api\AccountManagementInterface::class)) {
    eval('
        namespace Magento\Customer\Api;
        interface AccountManagementInterface {
            public function createAccount(
                \Magento\Customer\Api\Data\CustomerInterface $customer,
                ?string $password = null,
                string $redirectUrl = ""
            ): \Magento\Customer\Api\Data\CustomerInterface;
        }
    ');
}

if (!interface_exists(\Magento\Store\Api\Data\StoreInterface::class)) {
    eval('
        namespace Magento\Store\Api\Data;
        interface StoreInterface {
            public function getId();
            public function getCode();
        }
    ');
}

if (!interface_exists(\Magento\Store\Model\StoreManagerInterface::class)) {
    eval('
        namespace Magento\Store\Model;
        interface StoreManagerInterface {
            public function setCurrentStore($store);
            public function getStore($storeId = null): \Magento\Store\Api\Data\StoreInterface;
            public function getDefaultStoreView(): ?\Magento\Store\Api\Data\StoreInterface;
        }
    ');
}

if (!interface_exists(\Magento\Customer\Api\CustomerRepositoryInterface::class)) {
    eval('
        namespace Magento\Customer\Api;
        interface CustomerRepositoryInterface {
            public function save(\Magento\Customer\Api\Data\CustomerInterface $customer, ?string $passwordHash = null): \Magento\Customer\Api\Data\CustomerInterface;
            public function get(string $email, ?int $websiteId = null): \Magento\Customer\Api\Data\CustomerInterface;
            public function getById(int $customerId): \Magento\Customer\Api\Data\CustomerInterface;
            public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria): \Magento\Customer\Api\Data\CustomerSearchResultsInterface;
            public function delete(\Magento\Customer\Api\Data\CustomerInterface $customer): bool;
            public function deleteById(string $customerId): bool;
        }
    ');
}

if (!interface_exists(\Magento\Catalog\Api\Data\ProductInterface::class)) {
    eval('
        namespace Magento\Catalog\Api\Data;
        interface ProductInterface {
            public function getId();
            public function getSku(): ?string;
            public function setSku(string $sku): self;
            public function getName(): ?string;
            public function setName(string $name): self;
            public function getPrice(): ?float;
            public function setPrice(float $price): self;
            public function getAttributeSetId(): ?int;
            public function setAttributeSetId(int $attributeSetId): self;
            public function getStatus(): ?int;
            public function setStatus(int $status): self;
            public function getVisibility(): ?int;
            public function setVisibility(int $visibility): self;
            public function getTypeId(): ?string;
            public function setTypeId(string $typeId): self;
            public function getWeight(): ?float;
            public function setWeight(float $weight): self;
            public function setCustomAttribute(string $attributeCode, $attributeValue): self;
            public function setProductLinks(array $links): self;
        }
    ');
}

if (!class_exists(\Magento\Catalog\Api\Data\ProductInterfaceFactory::class)) {
    eval('
        namespace Magento\Catalog\Api\Data;
        class ProductInterfaceFactory {
            public function create(array $data = []): ProductInterface {
                throw new \RuntimeException("Stub: not implemented");
            }
        }
    ');
}

if (!interface_exists(\Magento\Catalog\Api\Data\ProductSearchResultsInterface::class)) {
    eval('
        namespace Magento\Catalog\Api\Data;
        interface ProductSearchResultsInterface {
            public function getItems(): array;
            public function getTotalCount(): int;
        }
    ');
}

if (!interface_exists(\Magento\Catalog\Api\ProductRepositoryInterface::class)) {
    eval('
        namespace Magento\Catalog\Api;
        interface ProductRepositoryInterface {
            public function save(\Magento\Catalog\Api\Data\ProductInterface $product): \Magento\Catalog\Api\Data\ProductInterface;
            public function get(string $sku): \Magento\Catalog\Api\Data\ProductInterface;
            public function getById(int $productId): \Magento\Catalog\Api\Data\ProductInterface;
            public function delete(\Magento\Catalog\Api\Data\ProductInterface $product): bool;
            public function deleteById(string $sku): bool;
            public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria): \Magento\Catalog\Api\Data\ProductSearchResultsInterface;
        }
    ');
}

if (!class_exists(\Magento\Catalog\Model\Product::class)) {
    eval('
        namespace Magento\Catalog\Model;
        class Product implements \Magento\Catalog\Api\Data\ProductInterface {
            public function getId() { return null; }
            public function getSku(): ?string { return null; }
            public function setSku(string $sku): self { return $this; }
            public function getName(): ?string { return null; }
            public function setName(string $name): self { return $this; }
            public function getPrice(): ?float { return null; }
            public function setPrice(float $price): self { return $this; }
            public function getAttributeSetId(): ?int { return null; }
            public function setAttributeSetId(int $attributeSetId): self { return $this; }
            public function getStatus(): ?int { return null; }
            public function setStatus(int $status): self { return $this; }
            public function getVisibility(): ?int { return null; }
            public function setVisibility(int $visibility): self { return $this; }
            public function getTypeId(): ?string { return null; }
            public function setTypeId(string $typeId): self { return $this; }
            public function getWeight(): ?float { return null; }
            public function setWeight(float $weight): self { return $this; }
            public function setCustomAttribute(string $attributeCode, $attributeValue): self { return $this; }
            public function setProductLinks(array $links): self { return $this; }
            public function addImageToMediaGallery($file, $mediaAttribute = null, $move = false, $exclude = true): self { return $this; }
            public function setStockData(array $stockData): self { return $this; }
            public function setWebsiteIds(array $websiteIds): self { return $this; }
            public function setData($key, $value = null): self { return $this; }
            public function getData($key = "", $index = null) { return null; }
        }
    ');
}

if (!class_exists(\Magento\Catalog\Model\Product\Attribute\Source\Status::class)) {
    eval('
        namespace Magento\Catalog\Model\Product\Attribute\Source;
        class Status {
            public const STATUS_ENABLED = 1;
            public const STATUS_DISABLED = 2;
        }
    ');
}

if (!class_exists(\Magento\Catalog\Model\Product\Type::class)) {
    eval('
        namespace Magento\Catalog\Model\Product;
        class Type {
            public const TYPE_SIMPLE = "simple";
            public const TYPE_BUNDLE = "bundle";
            public const TYPE_VIRTUAL = "virtual";
        }
    ');
}

if (!class_exists(\Magento\Catalog\Model\Product\Visibility::class)) {
    eval('
        namespace Magento\Catalog\Model\Product;
        class Visibility {
            public const VISIBILITY_NOT_VISIBLE = 1;
            public const VISIBILITY_IN_CATALOG = 2;
            public const VISIBILITY_IN_SEARCH = 3;
            public const VISIBILITY_BOTH = 4;
        }
    ');
}

if (!interface_exists(\Magento\Cms\Api\Data\PageInterface::class)) {
    eval('
        namespace Magento\Cms\Api\Data;
        interface PageInterface {
            public function getId();
            public function getIdentifier(): ?string;
            public function setIdentifier(string $identifier): self;
            public function getTitle(): ?string;
            public function setTitle(string $title): self;
            public function getContent(): ?string;
            public function setContent(string $content): self;
            public function setIsActive(bool $isActive): self;
            public function setStoreId($storeId): self;
        }
    ');
}

if (!class_exists(\Magento\Cms\Api\Data\PageInterfaceFactory::class)) {
    eval('
        namespace Magento\Cms\Api\Data;
        class PageInterfaceFactory {
            public function create(array $data = []): PageInterface {
                throw new \RuntimeException("Stub: not implemented");
            }
        }
    ');
}

if (!interface_exists(\Magento\Cms\Api\Data\PageSearchResultsInterface::class)) {
    eval('
        namespace Magento\Cms\Api\Data;
        interface PageSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface {
            public function getItems(): array;
            public function setItems(array $items): \Magento\Framework\Api\SearchResultsInterface;
        }
    ');
}

if (!interface_exists(\Magento\Cms\Api\PageRepositoryInterface::class)) {
    eval('
        namespace Magento\Cms\Api;
        interface PageRepositoryInterface {
            public function save(\Magento\Cms\Api\Data\PageInterface $page): \Magento\Cms\Api\Data\PageInterface;
            public function getById(int $pageId): \Magento\Cms\Api\Data\PageInterface;
            public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria): \Magento\Cms\Api\Data\PageSearchResultsInterface;
            public function delete(\Magento\Cms\Api\Data\PageInterface $page): bool;
            public function deleteById(int $pageId): bool;
        }
    ');
}

if (!interface_exists(\Magento\Cms\Api\Data\BlockInterface::class)) {
    eval('
        namespace Magento\Cms\Api\Data;
        interface BlockInterface {
            public function getId();
            public function getIdentifier(): ?string;
            public function setIdentifier(string $identifier): self;
            public function getTitle(): ?string;
            public function setTitle(string $title): self;
            public function getContent(): ?string;
            public function setContent(string $content): self;
            public function setIsActive(bool $isActive): self;
            public function setStoreId($storeId): self;
        }
    ');
}

if (!class_exists(\Magento\Cms\Api\Data\BlockInterfaceFactory::class)) {
    eval('
        namespace Magento\Cms\Api\Data;
        class BlockInterfaceFactory {
            public function create(array $data = []): BlockInterface {
                throw new \RuntimeException("Stub: not implemented");
            }
        }
    ');
}

if (!interface_exists(\Magento\Cms\Api\Data\BlockSearchResultsInterface::class)) {
    eval('
        namespace Magento\Cms\Api\Data;
        interface BlockSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface {
            public function getItems(): array;
            public function setItems(array $items): \Magento\Framework\Api\SearchResultsInterface;
        }
    ');
}

if (!interface_exists(\Magento\Cms\Api\BlockRepositoryInterface::class)) {
    eval('
        namespace Magento\Cms\Api;
        interface BlockRepositoryInterface {
            public function save(\Magento\Cms\Api\Data\BlockInterface $block): \Magento\Cms\Api\Data\BlockInterface;
            public function getById(int $blockId): \Magento\Cms\Api\Data\BlockInterface;
            public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria): \Magento\Cms\Api\Data\BlockSearchResultsInterface;
            public function delete(\Magento\Cms\Api\Data\BlockInterface $block): bool;
            public function deleteById(int $blockId): bool;
        }
    ');
}

// Quote/Sales stubs for OrderHandler tests

if (!interface_exists(\Magento\Quote\Api\Data\PaymentInterface::class)) {
    eval('
        namespace Magento\Quote\Api\Data;
        interface PaymentInterface {
            public function getMethod(): ?string;
            public function setMethod(string $method): self;
        }
    ');
}

if (!interface_exists(\Magento\Quote\Api\CartManagementInterface::class)) {
    eval('
        namespace Magento\Quote\Api;
        interface CartManagementInterface {
            public function createEmptyCart(): int;
            public function placeOrder(int $cartId, \Magento\Quote\Api\Data\PaymentInterface $paymentMethod = null): int;
        }
    ');
}

if (!interface_exists(\Magento\Quote\Api\Data\CartItemInterface::class)) {
    eval('
        namespace Magento\Quote\Api\Data;
        interface CartItemInterface {
            public function getItemId(): ?int;
            public function setQuoteId($quoteId): self;
            public function getSku(): ?string;
            public function setSku(string $sku): self;
            public function getQty(): float;
            public function setQty(float $qty): self;
        }
    ');
}

if (!class_exists(\Magento\Quote\Api\Data\CartItemInterfaceFactory::class)) {
    eval('
        namespace Magento\Quote\Api\Data;
        class CartItemInterfaceFactory {
            public function create(array $data = []): CartItemInterface {
                throw new \RuntimeException("Stub: not implemented");
            }
        }
    ');
}

if (!interface_exists(\Magento\Quote\Api\CartItemRepositoryInterface::class)) {
    eval('
        namespace Magento\Quote\Api;
        interface CartItemRepositoryInterface {
            public function getList(int $cartId): array;
            public function save(\Magento\Quote\Api\Data\CartItemInterface $cartItem): \Magento\Quote\Api\Data\CartItemInterface;
            public function deleteById(int $cartId, int $itemId): bool;
        }
    ');
}

if (!interface_exists(\Magento\Quote\Api\Data\AddressInterface::class)) {
    eval('
        namespace Magento\Quote\Api\Data;
        interface AddressInterface {
            public function addData(array $data): self;
            public function setCollectShippingRates(bool $flag): self;
            public function setShippingMethod(string $method): self;
        }
    ');
}

if (!class_exists(\Magento\Quote\Model\Quote\Payment::class)) {
    eval('
        namespace Magento\Quote\Model\Quote;
        class Payment {
            public function setMethod(string $method): self { return $this; }
            public function getMethod(): ?string { return null; }
        }
    ');
}

if (!interface_exists(\Magento\Quote\Api\Data\CartInterface::class)) {
    eval('
        namespace Magento\Quote\Api\Data;
        interface CartInterface {
            public function getId(): ?int;
            public function setStoreId($storeId);
            public function setCustomerEmail(string $email);
            public function setCustomerIsGuest(bool $isGuest);
            public function setCustomerFirstname(string $firstname);
            public function setCustomerLastname(string $lastname);
            public function getBillingAddress(): AddressInterface;
            public function getShippingAddress(): AddressInterface;
            public function getPayment(): \Magento\Quote\Model\Quote\Payment;
            public function collectTotals(): self;
        }
    ');
}

if (!interface_exists(\Magento\Quote\Api\CartRepositoryInterface::class)) {
    eval('
        namespace Magento\Quote\Api;
        interface CartRepositoryInterface {
            public function get(int $cartId): \Magento\Quote\Api\Data\CartInterface;
            public function save(\Magento\Quote\Api\Data\CartInterface $quote): void;
        }
    ');
}

if (!interface_exists(\Magento\Sales\Api\Data\OrderInterface::class)) {
    eval('
        namespace Magento\Sales\Api\Data;
        interface OrderInterface {
            public function getEntityId(): ?string;
            public function getIncrementId(): ?string;
            public function getState(): ?string;
        }
    ');
}

if (!class_exists(\Magento\Sales\Model\Order::class)) {
    eval('
        namespace Magento\Sales\Model;
        class Order implements \Magento\Sales\Api\Data\OrderInterface {
            public function getEntityId(): ?string { return null; }
            public function getIncrementId(): ?string { return null; }
            public function getState(): ?string { return null; }
            public function hold(): self { return $this; }
            public function cancel(): self { return $this; }
            public function setState(string $state): self { return $this; }
            public function setStatus(string $status): self { return $this; }
            public function setIsInProcess(bool $flag): self { return $this; }
            public function canInvoice(): bool { return true; }
            public function getAllItems(): array { return []; }
        }
    ');
}

if (!class_exists(\Magento\Sales\Model\Order\Item::class)) {
    eval('
        namespace Magento\Sales\Model\Order;
        class Item {
            public function getItemId() { return null; }
            public function getQtyOrdered() { return 0.0; }
        }
    ');
}

if (!interface_exists(\Magento\Sales\Api\Data\OrderSearchResultInterface::class)) {
    eval('
        namespace Magento\Sales\Api\Data;
        interface OrderSearchResultInterface {
            public function getItems(): array;
            public function getTotalCount(): int;
        }
    ');
}

if (!interface_exists(\Magento\Sales\Api\OrderRepositoryInterface::class)) {
    eval('
        namespace Magento\Sales\Api;
        interface OrderRepositoryInterface {
            public function get(int $id): \Magento\Sales\Api\Data\OrderInterface;
            public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria): \Magento\Sales\Api\Data\OrderSearchResultInterface;
            public function save(\Magento\Sales\Api\Data\OrderInterface $order): \Magento\Sales\Api\Data\OrderInterface;
            public function delete(\Magento\Sales\Api\Data\OrderInterface $order): bool;
        }
    ');
}

// CatalogInventory stubs for stock management

if (!interface_exists(\Magento\CatalogInventory\Api\Data\StockItemInterface::class)) {
    eval('
        namespace Magento\CatalogInventory\Api\Data;
        interface StockItemInterface {
            public function getQty(): ?float;
            public function setQty(float $qty): self;
            public function getIsInStock(): ?bool;
            public function setIsInStock(bool $isInStock): self;
        }
    ');
}

if (!interface_exists(\Magento\CatalogInventory\Api\StockRegistryInterface::class)) {
    eval('
        namespace Magento\CatalogInventory\Api;
        interface StockRegistryInterface {
            public function getStockItemBySku(string $productSku, ?int $scopeId = null): \Magento\CatalogInventory\Api\Data\StockItemInterface;
            public function updateStockItemBySku(string $productSku, \Magento\CatalogInventory\Api\Data\StockItemInterface $stockItem): int;
        }
    ');
}

if (!interface_exists(\Magento\Customer\Api\Data\AddressInterface::class)) {
    eval('
        namespace Magento\Customer\Api\Data;
        interface AddressInterface {
            public function getId();
            public function setCustomerId($customerId);
            public function setFirstname(string $firstname);
            public function setLastname(string $lastname);
            public function setStreet(array $street);
            public function setCity(string $city);
            public function setRegionId(int $regionId);
            public function setPostcode(string $postcode);
            public function setCountryId(string $countryId);
            public function setTelephone(string $telephone);
            public function setIsDefaultBilling(bool $isDefaultBilling);
            public function setIsDefaultShipping(bool $isDefaultShipping);
        }
    ');
}

if (!class_exists(\Magento\Customer\Api\Data\AddressInterfaceFactory::class)) {
    eval('
        namespace Magento\Customer\Api\Data;
        class AddressInterfaceFactory {
            public function create(array $data = []): AddressInterface {
                throw new \RuntimeException("Stub: not implemented");
            }
        }
    ');
}

if (!interface_exists(\Magento\Customer\Api\AddressRepositoryInterface::class)) {
    eval('
        namespace Magento\Customer\Api;
        interface AddressRepositoryInterface {
            public function save(\Magento\Customer\Api\Data\AddressInterface $address): \Magento\Customer\Api\Data\AddressInterface;
        }
    ');
}

// EAV stubs for configurable products

if (!interface_exists(\Magento\Eav\Api\Data\AttributeOptionInterface::class)) {
    eval('
        namespace Magento\Eav\Api\Data;
        interface AttributeOptionInterface {
            public function getValue(): ?string;
            public function getLabel(): ?string;
        }
    ');
}

if (!interface_exists(\Magento\Eav\Api\Data\AttributeInterface::class)) {
    eval('
        namespace Magento\Eav\Api\Data;
        interface AttributeInterface {
            public function getAttributeId(): ?int;
            public function getAttributeCode(): ?string;
            public function getDefaultFrontendLabel(): ?string;
            public function getFrontendLabel(): ?string;
            public function getOptions(): array;
        }
    ');
}

if (!class_exists(\Magento\Eav\Model\Config::class)) {
    eval('
        namespace Magento\Eav\Model;
        class Config {
            public function getAttribute(string $entityType, string $attributeCode): \Magento\Eav\Api\Data\AttributeInterface {
                throw new \RuntimeException("Stub: not implemented");
            }
        }
    ');
}

// ConfigurableProduct stubs

if (!interface_exists(\Magento\ConfigurableProduct\Api\LinkManagementInterface::class)) {
    eval('
        namespace Magento\ConfigurableProduct\Api;
        interface LinkManagementInterface {
            public function setChildren(string $sku, array $childSkus): bool;
        }
    ');
}

if (!interface_exists(\Magento\ConfigurableProduct\Api\Data\OptionValueInterface::class)) {
    eval('
        namespace Magento\ConfigurableProduct\Api\Data;
        interface OptionValueInterface {
            public function setValueIndex($id): self;
        }
    ');
}

if (!class_exists(\Magento\ConfigurableProduct\Api\Data\OptionValueInterfaceFactory::class)) {
    eval('
        namespace Magento\ConfigurableProduct\Api\Data;
        class OptionValueInterfaceFactory {
            public function create(array $data = []): OptionValueInterface {
                throw new \RuntimeException("Stub: not implemented");
            }
        }
    ');
}

if (!interface_exists(\Magento\ConfigurableProduct\Api\Data\OptionInterface::class)) {
    eval('
        namespace Magento\ConfigurableProduct\Api\Data;
        interface OptionInterface {
            public function setAttributeId(int $id): self;
            public function setLabel(string $label): self;
            public function setPosition(int $pos): self;
            public function setIsUseDefault(bool $b): self;
            public function setValues(array $values): self;
        }
    ');
}

if (!class_exists(\Magento\ConfigurableProduct\Api\Data\OptionInterfaceFactory::class)) {
    eval('
        namespace Magento\ConfigurableProduct\Api\Data;
        class OptionInterfaceFactory {
            public function create(array $data = []): OptionInterface {
                throw new \RuntimeException("Stub: not implemented");
            }
        }
    ');
}

if (!interface_exists(\Magento\ConfigurableProduct\Api\OptionRepositoryInterface::class)) {
    eval('
        namespace Magento\ConfigurableProduct\Api;
        interface OptionRepositoryInterface {
            public function save(string $sku, \Magento\ConfigurableProduct\Api\Data\OptionInterface $option): int;
        }
    ');
}

// Bundle product stubs

if (!interface_exists(\Magento\Bundle\Api\Data\OptionInterface::class)) {
    eval('
        namespace Magento\Bundle\Api\Data;
        interface OptionInterface {
            public function setTitle(string $title): self;
            public function setType(string $type): self;
            public function setRequired(bool $required): self;
            public function setSku(string $sku): self;
            public function setPosition(int $position): self;
            public function getOptionId();
        }
    ');
}

if (!class_exists(\Magento\Bundle\Api\Data\OptionInterfaceFactory::class)) {
    eval('
        namespace Magento\Bundle\Api\Data;
        class OptionInterfaceFactory {
            public function create(array $data = []): OptionInterface {
                throw new \RuntimeException("Stub: not implemented");
            }
        }
    ');
}

if (!interface_exists(\Magento\Bundle\Api\Data\LinkInterface::class)) {
    eval('
        namespace Magento\Bundle\Api\Data;
        interface LinkInterface {
            public function setSku(string $sku): self;
            public function setQty($qty): self;
            public function setPriceType(int $priceType): self;
            public function setPrice($price): self;
            public function setIsDefault(bool $isDefault): self;
            public function setCanChangeQuantity(int $canChangeQuantity): self;
        }
    ');
}

if (!class_exists(\Magento\Bundle\Api\Data\LinkInterfaceFactory::class)) {
    eval('
        namespace Magento\Bundle\Api\Data;
        class LinkInterfaceFactory {
            public function create(array $data = []): LinkInterface {
                throw new \RuntimeException("Stub: not implemented");
            }
        }
    ');
}

if (!interface_exists(\Magento\Bundle\Api\ProductOptionRepositoryInterface::class)) {
    eval('
        namespace Magento\Bundle\Api;
        interface ProductOptionRepositoryInterface {
            public function save(\Magento\Catalog\Api\Data\ProductInterface $product, \Magento\Bundle\Api\Data\OptionInterface $option): int;
        }
    ');
}

if (!interface_exists(\Magento\Bundle\Api\ProductLinkManagementInterface::class)) {
    eval('
        namespace Magento\Bundle\Api;
        interface ProductLinkManagementInterface {
            public function addChild(\Magento\Catalog\Api\Data\ProductInterface $product, int $optionId, \Magento\Bundle\Api\Data\LinkInterface $link): int;
        }
    ');
}

if (!class_exists(\Magento\Framework\Api\Filter::class)) {
    eval('
        namespace Magento\Framework\Api;
        class Filter {}
    ');
}

if (!class_exists(\Magento\Framework\Api\FilterBuilder::class)) {
    eval('
        namespace Magento\Framework\Api;
        class FilterBuilder {
            public function setField(string $field): self { return $this; }
            public function setValue($value): self { return $this; }
            public function setConditionType(string $conditionType): self { return $this; }
            public function create(): Filter { return new Filter(); }
        }
    ');
}

if (!interface_exists(\Magento\Catalog\Api\Data\ProductLinkInterface::class)) {
    eval('
        namespace Magento\Catalog\Api\Data;
        interface ProductLinkInterface {
            public function setSku(string $sku): self;
            public function setLinkedProductSku(string $linkedProductSku): self;
            public function setLinkType(string $linkType): self;
            public function setPosition(int $position): self;
            public function setLinkedProductType(string $linkedProductType): self;
        }
    ');
}

if (!class_exists(\Magento\Catalog\Api\Data\ProductLinkInterfaceFactory::class)) {
    eval('
        namespace Magento\Catalog\Api\Data;
        class ProductLinkInterfaceFactory {
            public function create(array $data = []): ProductLinkInterface {
                throw new \RuntimeException("Stub: not implemented");
            }
        }
    ');
}

// Downloadable product stubs

if (!interface_exists(\Magento\Downloadable\Api\Data\File\ContentInterface::class)) {
    eval('
        namespace Magento\Downloadable\Api\Data\File;
        interface ContentInterface {
            public function setFileData(string $fileData): self;
            public function setName(string $name): self;
        }
    ');
}

if (!class_exists(\Magento\Downloadable\Api\Data\File\ContentInterfaceFactory::class)) {
    eval('
        namespace Magento\Downloadable\Api\Data\File;
        class ContentInterfaceFactory {
            public function create(array $data = []): ContentInterface {
                throw new \RuntimeException("Stub: not implemented");
            }
        }
    ');
}

if (!interface_exists(\Magento\Downloadable\Api\Data\LinkInterface::class)) {
    eval('
        namespace Magento\Downloadable\Api\Data;
        interface LinkInterface {
            public function setTitle(string $title): self;
            public function setPrice(float $price): self;
            public function setIsShareable(int $isShareable): self;
            public function setNumberOfDownloads(int $numberOfDownloads): self;
            public function setSortOrder(int $sortOrder): self;
            public function setLinkType(string $linkType): self;
            public function setLinkFile(string $linkFile): self;
            public function setSampleType(string $sampleType): self;
            public function setSampleFile(string $sampleFile): self;
            public function setLinkFileContent(\Magento\Downloadable\Api\Data\File\ContentInterface $content): self;
            public function setSampleFileContent(\Magento\Downloadable\Api\Data\File\ContentInterface $content): self;
        }
    ');
}

if (!class_exists(\Magento\Downloadable\Api\Data\LinkInterfaceFactory::class)) {
    eval('
        namespace Magento\Downloadable\Api\Data;
        class LinkInterfaceFactory {
            public function create(array $data = []): LinkInterface {
                throw new \RuntimeException("Stub: not implemented");
            }
        }
    ');
}

if (!interface_exists(\Magento\Downloadable\Api\LinkRepositoryInterface::class)) {
    eval('
        namespace Magento\Downloadable\Api;
        interface LinkRepositoryInterface {
            public function save(string $sku, \Magento\Downloadable\Api\Data\LinkInterface $link, bool $isGlobalScopeContent = true): int;
        }
    ');
}

// Invoice / Transaction stubs for order state transitions

if (!class_exists(\Magento\Sales\Model\Order\Invoice::class)) {
    eval('
        namespace Magento\Sales\Model\Order;
        class Invoice {
            public const CAPTURE_OFFLINE = "offline";
            public function setRequestedCaptureCase(string $case): self { return $this; }
            public function register(): self { return $this; }
        }
    ');
}

if (!class_exists(\Magento\Sales\Model\Service\InvoiceService::class)) {
    eval('
        namespace Magento\Sales\Model\Service;
        class InvoiceService {
            public function prepareInvoice(\Magento\Sales\Api\Data\OrderInterface $order, array $qtys = []): \Magento\Sales\Model\Order\Invoice {
                throw new \RuntimeException("Stub: not implemented");
            }
        }
    ');
}

if (!class_exists(\Magento\Framework\DB\Transaction::class)) {
    eval('
        namespace Magento\Framework\DB;
        class Transaction {
            public function addObject($object): self { return $this; }
            public function save(): self { return $this; }
        }
    ');
}

if (!class_exists(\Magento\Framework\DB\TransactionFactory::class)) {
    eval('
        namespace Magento\Framework\DB;
        class TransactionFactory {
            public function create(array $data = []): \Magento\Framework\DB\Transaction {
                throw new \RuntimeException("Stub: not implemented");
            }
        }
    ');
}

if (!class_exists(\Magento\Sales\Model\Order\Shipment::class)) {
    eval('
        namespace Magento\Sales\Model\Order;
        class Shipment {
            public function register(): self { return $this; }
        }
    ');
}

if (!class_exists(\Magento\Sales\Model\Order\ShipmentFactory::class)) {
    eval('
        namespace Magento\Sales\Model\Order;
        class ShipmentFactory {
            public function create(\Magento\Sales\Api\Data\OrderInterface $order, array $items = [], $tracks = []): \Magento\Sales\Model\Order\Shipment {
                throw new \RuntimeException("Stub: not implemented");
            }
        }
    ');
}

if (!class_exists(\Magento\Sales\Model\Order\Creditmemo::class)) {
    eval('
        namespace Magento\Sales\Model\Order;
        class Creditmemo {
            public function getId() { return null; }
        }
    ');
}

if (!class_exists(\Magento\Sales\Model\Order\CreditmemoFactory::class)) {
    eval('
        namespace Magento\Sales\Model\Order;
        class CreditmemoFactory {
            public function createByOrder(\Magento\Sales\Api\Data\OrderInterface $order, array $data = []): \Magento\Sales\Model\Order\Creditmemo {
                throw new \RuntimeException("Stub: not implemented");
            }
        }
    ');
}

if (!interface_exists(\Magento\Sales\Api\CreditmemoManagementInterface::class)) {
    eval('
        namespace Magento\Sales\Api;
        interface CreditmemoManagementInterface {
            public function refund(\Magento\Sales\Model\Order\Creditmemo $creditmemo, bool $offlineRequested = false): \Magento\Sales\Model\Order\Creditmemo;
        }
    ');
}

if (!class_exists(\Magento\Review\Model\Review::class)) {
    // setEntityPkValue/setStatusId/setTitle/setDetail/setNickname/setStoreId/setStores are
    // intentionally omitted: in real Magento those go through DataObject::__call, so tests
    // must declare them via getMockBuilder()->addMethods([...]). setEntityId is a real
    // declared method on Magento\Review\Model\Review and stays on the stub to match.
    eval('
        namespace Magento\Review\Model;
        class Review {
            public const ENTITY_PRODUCT_CODE = "product";
            public const STATUS_APPROVED = 1;
            public const STATUS_PENDING = 2;
            public const STATUS_NOT_APPROVED = 3;
            public function getEntityIdByCode(string $code): int { return 1; }
            public function setEntityId($id): self { return $this; }
            public function save(): self { return $this; }
            public function getId() { return 1; }
        }
    ');
}

if (!class_exists(\Magento\Review\Model\ReviewFactory::class)) {
    eval('
        namespace Magento\Review\Model;
        class ReviewFactory {
            public function create(array $data = []): Review {
                throw new \RuntimeException("Stub: not implemented");
            }
        }
    ');
}

if (!class_exists(\Magento\Review\Model\Rating::class)) {
    eval('
        namespace Magento\Review\Model;
        class Rating {
            public function getResourceCollection() { return $this; }
            public function addEntityFilter(string $code): self { return $this; }
            public function setPositionOrder(): self { return $this; }
            public function load(): array { return []; }
            public function getOptions(): array { return []; }
            public function setReviewId($id): self { return $this; }
            public function addOptionVote($optionId, $productId): self { return $this; }
        }
    ');
}

if (!class_exists(\Magento\Review\Model\RatingFactory::class)) {
    eval('
        namespace Magento\Review\Model;
        class RatingFactory {
            public function create(array $data = []): Rating {
                throw new \RuntimeException("Stub: not implemented");
            }
        }
    ');
}

if (!class_exists(\Magento\Framework\DB\Select::class)) {
    eval('
        namespace Magento\Framework\DB;
        class Select {
            public function from($table, $columns = "*") { return $this; }
            public function join($table, $condition, $columns = "*") { return $this; }
            public function where(string $cond, $value = null) { return $this; }
        }
    ');
}

if (!interface_exists(\Magento\Framework\DB\Adapter\AdapterInterface::class)) {
    eval('
        namespace Magento\Framework\DB\Adapter;
        interface AdapterInterface {
            public function select(): \Magento\Framework\DB\Select;
            public function fetchCol($select, $bind = []): array;
            public function fetchOne($select, $bind = []);
            public function delete(string $table, $where = ""): int;
            public function beginTransaction(): self;
            public function commit(): self;
            public function rollBack(): self;
        }
    ');
}

if (!class_exists(\Magento\Framework\App\ResourceConnection::class)) {
    eval('
        namespace Magento\Framework\App;
        class ResourceConnection {
            public function getConnection(string $resourceName = "default"): \Magento\Framework\DB\Adapter\AdapterInterface {
                throw new \RuntimeException("Stub: not implemented");
            }
            public function getTableName(string $modelEntity, string $connectionName = "default"): string {
                return $modelEntity;
            }
        }
    ');
}

if (!class_exists(\Zend_Db_Expr::class)) {
    eval('
        class Zend_Db_Expr {
            private string $expr;
            public function __construct(string $expr) { $this->expr = $expr; }
            public function __toString(): string { return $this->expr; }
        }
    ');
}

require dirname(__DIR__) . '/vendor/autoload.php';
