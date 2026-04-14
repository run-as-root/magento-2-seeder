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
            public function getRoot(): string { return ""; }
            public function getPath(string $code): string { return ""; }
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

require dirname(__DIR__) . '/vendor/autoload.php';
