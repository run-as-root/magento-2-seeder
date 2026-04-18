<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler;

use RunAsRoot\Seeder\EntityHandler\CustomerHandler;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\Data\CustomerSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use PHPUnit\Framework\TestCase;

final class CustomerHandlerTest extends TestCase
{
    public function test_get_type_returns_customer(): void
    {
        $handler = $this->createHandler();

        $this->assertSame('customer', $handler->getType());
    }

    public function test_create_uses_account_management_to_create_customer(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->expects($this->once())->method('setEmail')->with('john@test.com')->willReturnSelf();
        $customer->expects($this->once())->method('setFirstname')->with('John')->willReturnSelf();
        $customer->expects($this->once())->method('setLastname')->with('Doe')->willReturnSelf();
        $customer->expects($this->once())->method('setWebsiteId')->with(1)->willReturnSelf();
        $customer->expects($this->once())->method('setStoreId')->with(1)->willReturnSelf();
        $customer->expects($this->once())->method('setGroupId')->with(1)->willReturnSelf();

        $factory = $this->createMock(CustomerInterfaceFactory::class);
        $factory->method('create')->willReturn($customer);

        $createdCustomer = $this->createMock(CustomerInterface::class);

        $accountManagement = $this->createMock(AccountManagementInterface::class);
        $accountManagement->expects($this->once())
            ->method('createAccount')
            ->with($customer, 'Test1234!')
            ->willReturn($createdCustomer);

        $handler = $this->createHandler(
            accountManagement: $accountManagement,
            customerFactory: $factory,
        );

        $handler->create([
            'email' => 'john@test.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'password' => 'Test1234!',
        ]);
    }

    public function test_clean_deletes_all_customers(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn('42');

        $searchResults = $this->createMock(CustomerSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([$customer]);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);

        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('setPageSize')->willReturnSelf();
        $searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepository->method('getList')->with($searchCriteria)->willReturn($searchResults);
        $customerRepository->expects($this->once())->method('deleteById')->with('42');

        $handler = $this->createHandler(
            customerRepository: $customerRepository,
            searchCriteriaBuilder: $searchCriteriaBuilder,
        );

        $handler->clean();
    }

    public function test_create_saves_addresses_after_account_creation(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('setEmail')->willReturnSelf();
        $customer->method('setFirstname')->willReturnSelf();
        $customer->method('setLastname')->willReturnSelf();
        $customer->method('setWebsiteId')->willReturnSelf();
        $customer->method('setStoreId')->willReturnSelf();
        $customer->method('setGroupId')->willReturnSelf();

        $createdCustomer = $this->createMock(CustomerInterface::class);
        $createdCustomer->method('getId')->willReturn('99');

        $factory = $this->createMock(CustomerInterfaceFactory::class);
        $factory->method('create')->willReturn($customer);

        $accountManagement = $this->createMock(AccountManagementInterface::class);
        $accountManagement->method('createAccount')->willReturn($createdCustomer);

        $address = $this->createMock(AddressInterface::class);
        $address->method('setCustomerId')->willReturnSelf();
        $address->method('setFirstname')->willReturnSelf();
        $address->method('setLastname')->willReturnSelf();
        $address->method('setStreet')->willReturnSelf();
        $address->method('setCity')->willReturnSelf();
        $address->method('setRegionId')->willReturnSelf();
        $address->method('setPostcode')->willReturnSelf();
        $address->method('setCountryId')->willReturnSelf();
        $address->method('setTelephone')->willReturnSelf();
        $address->method('setIsDefaultBilling')->willReturnSelf();
        $address->method('setIsDefaultShipping')->willReturnSelf();

        $addressFactory = $this->createMock(AddressInterfaceFactory::class);
        $addressFactory->expects($this->once())->method('create')->willReturn($address);

        $addressRepository = $this->createMock(AddressRepositoryInterface::class);
        $addressRepository->expects($this->once())->method('save')->with($address);

        $handler = $this->createHandler(
            accountManagement: $accountManagement,
            customerFactory: $factory,
            addressRepository: $addressRepository,
            addressFactory: $addressFactory,
        );

        $handler->create([
            'email' => 'jane@test.com',
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'password' => 'Test1234!',
            'addresses' => [
                [
                    'firstname' => 'Jane',
                    'lastname' => 'Doe',
                    'street' => ['456 Oak Ave'],
                    'city' => 'Springfield',
                    'region_id' => 12,
                    'postcode' => '62704',
                    'country_id' => 'US',
                    'telephone' => '555-0199',
                    'default_billing' => true,
                    'default_shipping' => true,
                ],
            ],
        ]);
    }

    private function createHandler(
        ?AccountManagementInterface $accountManagement = null,
        ?CustomerInterfaceFactory $customerFactory = null,
        ?CustomerRepositoryInterface $customerRepository = null,
        ?SearchCriteriaBuilder $searchCriteriaBuilder = null,
        ?AddressRepositoryInterface $addressRepository = null,
        ?AddressInterfaceFactory $addressFactory = null,
    ): CustomerHandler {
        return new CustomerHandler(
            $accountManagement ?? $this->createMock(AccountManagementInterface::class),
            $customerFactory ?? $this->createMock(CustomerInterfaceFactory::class),
            $customerRepository ?? $this->createMock(CustomerRepositoryInterface::class),
            $searchCriteriaBuilder ?? $this->createMock(SearchCriteriaBuilder::class),
            $addressRepository ?? $this->createMock(AddressRepositoryInterface::class),
            $addressFactory ?? $this->createMock(AddressInterfaceFactory::class),
        );
    }
}
