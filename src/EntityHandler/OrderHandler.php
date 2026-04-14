<?php

declare(strict_types=1);

namespace DavidLambauer\Seeder\EntityHandler;

use DavidLambauer\Seeder\Api\EntityHandlerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartItemInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;

class OrderHandler implements EntityHandlerInterface
{
    public function __construct(
        private readonly CartManagementInterface $cartManagement,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CartItemInterfaceFactory $cartItemFactory,
        private readonly CartItemRepositoryInterface $cartItemRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
    ) {
    }

    public function getType(): string
    {
        return 'order';
    }

    public function create(array $data): void
    {
        $cartId = $this->cartManagement->createEmptyCart();

        foreach ($data['items'] as $itemData) {
            $cartItem = $this->cartItemFactory->create();
            $cartItem->setQuoteId($cartId)
                ->setSku($itemData['sku'])
                ->setQty($itemData['qty'] ?? 1);
            $this->cartItemRepository->save($cartItem);
        }

        $quote = $this->cartRepository->get($cartId);
        $quote->setCustomerEmail($data['customer_email'] ?? 'guest@example.com');
        $quote->setCustomerIsGuest(true);
        $quote->setCustomerFirstname($data['firstname'] ?? 'Seed');
        $quote->setCustomerLastname($data['lastname'] ?? 'Customer');

        $addressData = [
            'firstname' => $data['firstname'] ?? 'Seed',
            'lastname' => $data['lastname'] ?? 'Customer',
            'street' => $data['street'] ?? '123 Main St',
            'city' => $data['city'] ?? 'New York',
            'region_id' => $data['region_id'] ?? 43,
            'postcode' => $data['postcode'] ?? '10001',
            'country_id' => $data['country_id'] ?? 'US',
            'telephone' => $data['telephone'] ?? '555-0100',
        ];

        $quote->getBillingAddress()->addData($addressData);

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->addData($addressData);
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->setShippingMethod($data['shipping_method'] ?? 'flatrate_flatrate');

        $quote->getPayment()->setMethod($data['payment_method'] ?? 'checkmo');
        $quote->collectTotals();
        $this->cartRepository->save($quote);

        $this->cartManagement->placeOrder($cartId);
    }

    public function clean(): void
    {
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $orders = $this->orderRepository->getList($searchCriteria);

        foreach ($orders->getItems() as $order) {
            $this->orderRepository->delete($order);
        }
    }
}
