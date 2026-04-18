<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler;

use RunAsRoot\Seeder\Api\EntityHandlerInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;

class CustomerHandler implements EntityHandlerInterface
{
    public function __construct(
        private readonly AccountManagementInterface $accountManagement,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly AddressInterfaceFactory $addressFactory,
    ) {
    }

    public function getType(): string
    {
        return 'customer';
    }

    public function create(array $data): void
    {
        $customer = $this->customerFactory->create();
        $customer->setEmail($data['email'])
            ->setFirstname($data['firstname'])
            ->setLastname($data['lastname'])
            ->setWebsiteId($data['website_id'] ?? 1)
            ->setStoreId($data['store_id'] ?? 1)
            ->setGroupId($data['group_id'] ?? 1);

        if (!empty($data['dob'])) {
            $customer->setDob($data['dob']);
        }

        $createdCustomer = $this->accountManagement->createAccount(
            $customer,
            $data['password'] ?? null,
        );

        if (!empty($data['addresses'])) {
            foreach ($data['addresses'] as $addressData) {
                $address = $this->addressFactory->create();
                $address->setCustomerId($createdCustomer->getId())
                    ->setFirstname($addressData['firstname'] ?? $data['firstname'])
                    ->setLastname($addressData['lastname'] ?? $data['lastname'])
                    ->setStreet($addressData['street'] ?? ['123 Main St'])
                    ->setCity($addressData['city'] ?? 'New York')
                    ->setRegionId($addressData['region_id'] ?? 43)
                    ->setPostcode($addressData['postcode'] ?? '10001')
                    ->setCountryId($addressData['country_id'] ?? 'US')
                    ->setTelephone($addressData['telephone'] ?? '555-0100')
                    ->setIsDefaultBilling($addressData['default_billing'] ?? false)
                    ->setIsDefaultShipping($addressData['default_shipping'] ?? false);
                $this->addressRepository->save($address);
            }
        }
    }

    public function clean(): void
    {
        $searchCriteria = $this->searchCriteriaBuilder->setPageSize(10000)->create();
        $customers = $this->customerRepository->getList($searchCriteria);

        foreach ($customers->getItems() as $customer) {
            $this->customerRepository->deleteById($customer->getId());
        }
    }
}
