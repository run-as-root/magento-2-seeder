<?php

declare(strict_types=1);

namespace DavidLambauer\Seeder\EntityHandler;

use DavidLambauer\Seeder\Api\EntityHandlerInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;

class CustomerHandler implements EntityHandlerInterface
{
    public function __construct(
        private readonly AccountManagementInterface $accountManagement,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
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

        $this->accountManagement->createAccount(
            $customer,
            $data['password'] ?? null,
        );
    }

    public function clean(): void
    {
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $customers = $this->customerRepository->getList($searchCriteria);

        foreach ($customers->getItems() as $customer) {
            $this->customerRepository->deleteById($customer->getId());
        }
    }
}
