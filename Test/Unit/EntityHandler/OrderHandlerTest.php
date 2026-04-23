<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler;

use RunAsRoot\Seeder\EntityHandler\Order\StateTransitionInterface;
use RunAsRoot\Seeder\EntityHandler\Order\StateTransitionPool;
use RunAsRoot\Seeder\EntityHandler\OrderHandler;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Api\Data\CartItemInterfaceFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

final class OrderHandlerTest extends TestCase
{
    public function test_get_type_returns_order(): void
    {
        $handler = $this->createHandler();

        $this->assertSame('order', $handler->getType());
    }

    public function test_create_places_order_through_quote_flow(): void
    {
        $cartManagement = $this->createMock(CartManagementInterface::class);
        $cartManagement->expects($this->once())
            ->method('createEmptyCart')
            ->willReturn(123);
        $cartManagement->expects($this->once())
            ->method('placeOrder')
            ->with(123)
            ->willReturn(456);

        $cartItem = $this->createMock(CartItemInterface::class);
        $cartItem->method('setQuoteId')->willReturnSelf();
        $cartItem->method('setSku')->willReturnSelf();
        $cartItem->method('setQty')->willReturnSelf();

        $cartItemFactory = $this->createMock(CartItemInterfaceFactory::class);
        $cartItemFactory->method('create')->willReturn($cartItem);

        $cartItemRepository = $this->createMock(CartItemRepositoryInterface::class);
        $cartItemRepository->expects($this->once())->method('save');

        $billingAddress = $this->createAddressMock();
        $billingAddress->method('addData')->willReturnSelf();

        $shippingAddress = $this->createAddressMock();
        $shippingAddress->method('addData')->willReturnSelf();
        $shippingAddress->method('setCollectShippingRates')->willReturnSelf();
        $shippingAddress->method('setShippingMethod')->willReturnSelf();

        $payment = $this->createMock(\Magento\Quote\Model\Quote\Payment::class);
        $payment->method('setMethod')->willReturnSelf();

        $quote = $this->createMock(CartInterface::class);
        $quote->method('setStoreId')->willReturnSelf();
        $quote->method('setCustomerEmail')->willReturnSelf();
        $quote->method('setCustomerIsGuest')->willReturnSelf();
        $quote->method('setCustomerFirstname')->willReturnSelf();
        $quote->method('setCustomerLastname')->willReturnSelf();
        $quote->method('getBillingAddress')->willReturn($billingAddress);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('collectTotals')->willReturnSelf();

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->method('get')->with(123)->willReturn($quote);

        $loadedOrder = $this->createMock(OrderInterface::class);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('get')->with(456)->willReturn($loadedOrder);

        $handler = $this->createHandler(
            cartManagement: $cartManagement,
            cartItemFactory: $cartItemFactory,
            cartItemRepository: $cartItemRepository,
            cartRepository: $cartRepository,
            orderRepository: $orderRepository,
        );

        $handler->create([
            'customer_email' => 'test@test.com',
            'items' => [
                ['sku' => 'TEST-001', 'qty' => 2],
            ],
        ]);
    }

    public function test_create_routes_order_through_state_transition_after_placeOrder(): void
    {
        $cartManagement = $this->createMock(CartManagementInterface::class);
        $cartManagement->method('createEmptyCart')->willReturn(321);
        $cartManagement->expects($this->once())
            ->method('placeOrder')
            ->with(321)
            ->willReturn(999);

        $cartItem = $this->createMock(CartItemInterface::class);
        $cartItem->method('setQuoteId')->willReturnSelf();
        $cartItem->method('setSku')->willReturnSelf();
        $cartItem->method('setQty')->willReturnSelf();

        $cartItemFactory = $this->createMock(CartItemInterfaceFactory::class);
        $cartItemFactory->method('create')->willReturn($cartItem);

        $cartItemRepository = $this->createMock(CartItemRepositoryInterface::class);

        $billingAddress = $this->createAddressMock();
        $billingAddress->method('addData')->willReturnSelf();

        $shippingAddress = $this->createAddressMock();
        $shippingAddress->method('addData')->willReturnSelf();
        $shippingAddress->method('setCollectShippingRates')->willReturnSelf();
        $shippingAddress->method('setShippingMethod')->willReturnSelf();

        $payment = $this->createMock(\Magento\Quote\Model\Quote\Payment::class);
        $payment->method('setMethod')->willReturnSelf();

        $quote = $this->createMock(CartInterface::class);
        $quote->method('setStoreId')->willReturnSelf();
        $quote->method('setCustomerEmail')->willReturnSelf();
        $quote->method('setCustomerIsGuest')->willReturnSelf();
        $quote->method('setCustomerFirstname')->willReturnSelf();
        $quote->method('setCustomerLastname')->willReturnSelf();
        $quote->method('getBillingAddress')->willReturn($billingAddress);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('collectTotals')->willReturnSelf();

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->method('get')->with(321)->willReturn($quote);

        $loadedOrder = $this->createMock(OrderInterface::class);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->expects($this->once())
            ->method('get')
            ->with(999)
            ->willReturn($loadedOrder);

        $completeTransition = $this->createMock(StateTransitionInterface::class);
        $completeTransition->expects($this->once())
            ->method('apply')
            ->with(
                $loadedOrder,
                $this->callback(static fn (array $data): bool => ($data['order_state'] ?? null) === 'complete')
            );

        $pool = new StateTransitionPool(['complete' => $completeTransition]);

        $handler = $this->createHandler(
            cartManagement: $cartManagement,
            cartItemFactory: $cartItemFactory,
            cartItemRepository: $cartItemRepository,
            cartRepository: $cartRepository,
            orderRepository: $orderRepository,
            transitionPool: $pool,
        );

        $handler->create([
            'customer_email' => 'test@test.com',
            'order_state' => 'complete',
            'items' => [
                ['sku' => 'TEST-001', 'qty' => 1],
            ],
        ]);
    }

    public function test_create_skips_transition_when_state_unknown_or_new(): void
    {
        $cartManagement = $this->createMock(CartManagementInterface::class);
        $cartManagement->method('createEmptyCart')->willReturn(111);
        $cartManagement->expects($this->once())
            ->method('placeOrder')
            ->with(111)
            ->willReturn(222);

        $cartItem = $this->createMock(CartItemInterface::class);
        $cartItem->method('setQuoteId')->willReturnSelf();
        $cartItem->method('setSku')->willReturnSelf();
        $cartItem->method('setQty')->willReturnSelf();

        $cartItemFactory = $this->createMock(CartItemInterfaceFactory::class);
        $cartItemFactory->method('create')->willReturn($cartItem);

        $cartItemRepository = $this->createMock(CartItemRepositoryInterface::class);

        $billingAddress = $this->createAddressMock();
        $billingAddress->method('addData')->willReturnSelf();

        $shippingAddress = $this->createAddressMock();
        $shippingAddress->method('addData')->willReturnSelf();
        $shippingAddress->method('setCollectShippingRates')->willReturnSelf();
        $shippingAddress->method('setShippingMethod')->willReturnSelf();

        $payment = $this->createMock(\Magento\Quote\Model\Quote\Payment::class);
        $payment->method('setMethod')->willReturnSelf();

        $quote = $this->createMock(CartInterface::class);
        $quote->method('setStoreId')->willReturnSelf();
        $quote->method('setCustomerEmail')->willReturnSelf();
        $quote->method('setCustomerIsGuest')->willReturnSelf();
        $quote->method('setCustomerFirstname')->willReturnSelf();
        $quote->method('setCustomerLastname')->willReturnSelf();
        $quote->method('getBillingAddress')->willReturn($billingAddress);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('collectTotals')->willReturnSelf();

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->method('get')->with(111)->willReturn($quote);

        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->expects($this->never())->method('get');

        // Pool intentionally does NOT register 'new' so lookup short-circuits.
        $pool = new StateTransitionPool([]);

        $handler = $this->createHandler(
            cartManagement: $cartManagement,
            cartItemFactory: $cartItemFactory,
            cartItemRepository: $cartItemRepository,
            cartRepository: $cartRepository,
            orderRepository: $orderRepository,
            transitionPool: $pool,
        );

        $handler->create([
            'customer_email' => 'test@test.com',
            'order_state' => 'new',
            'items' => [
                ['sku' => 'TEST-001', 'qty' => 1],
            ],
        ]);
    }

    public function test_clean_deletes_all_orders(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn('1');

        $searchResults = $this->createMock(OrderSearchResultInterface::class);
        $searchResults->method('getItems')->willReturn([$order]);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);

        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('setPageSize')->willReturnSelf();
        $searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('getList')->willReturn($searchResults);
        $orderRepository->expects($this->once())->method('delete')->with($order);

        $handler = $this->createHandler(
            orderRepository: $orderRepository,
            searchCriteriaBuilder: $searchCriteriaBuilder,
        );

        $handler->clean();
    }

    private function createAddressMock(): \Magento\Quote\Api\Data\AddressInterface
    {
        return $this->getMockBuilder(\Magento\Quote\Api\Data\AddressInterface::class)
            ->disableOriginalConstructor()
            ->addMethods(['addData', 'setCollectShippingRates', 'setShippingMethod'])
            ->getMockForAbstractClass();
    }

    private function createHandler(
        ?CartManagementInterface $cartManagement = null,
        ?CartRepositoryInterface $cartRepository = null,
        ?CartItemInterfaceFactory $cartItemFactory = null,
        ?CartItemRepositoryInterface $cartItemRepository = null,
        ?OrderRepositoryInterface $orderRepository = null,
        ?SearchCriteriaBuilder $searchCriteriaBuilder = null,
        ?StoreManagerInterface $storeManager = null,
        ?StateTransitionPool $transitionPool = null,
    ): OrderHandler {
        if ($storeManager === null) {
            $store = $this->createMock(StoreInterface::class);
            $store->method('getId')->willReturn(1);
            $storeManager = $this->createMock(StoreManagerInterface::class);
            $storeManager->method('getDefaultStoreView')->willReturn($store);
        }

        if ($transitionPool === null) {
            $noopTransition = $this->createMock(StateTransitionInterface::class);
            $transitionPool = new StateTransitionPool(['new' => $noopTransition]);
        }

        return new OrderHandler(
            $cartManagement ?? $this->createMock(CartManagementInterface::class),
            $cartRepository ?? $this->createMock(CartRepositoryInterface::class),
            $cartItemFactory ?? $this->createMock(CartItemInterfaceFactory::class),
            $cartItemRepository ?? $this->createMock(CartItemRepositoryInterface::class),
            $orderRepository ?? $this->createMock(OrderRepositoryInterface::class),
            $searchCriteriaBuilder ?? $this->createMock(SearchCriteriaBuilder::class),
            $storeManager,
            $transitionPool,
        );
    }
}
