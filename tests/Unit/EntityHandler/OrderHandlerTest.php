<?php

declare(strict_types=1);

namespace DavidLambauer\Seeder\Test\Unit\EntityHandler;

use DavidLambauer\Seeder\EntityHandler\OrderHandler;
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
            ->with(123);

        $cartItem = $this->createMock(CartItemInterface::class);
        $cartItem->method('setQuoteId')->willReturnSelf();
        $cartItem->method('setSku')->willReturnSelf();
        $cartItem->method('setQty')->willReturnSelf();

        $cartItemFactory = $this->createMock(CartItemInterfaceFactory::class);
        $cartItemFactory->method('create')->willReturn($cartItem);

        $cartItemRepository = $this->createMock(CartItemRepositoryInterface::class);
        $cartItemRepository->expects($this->once())->method('save');

        $billingAddress = $this->createMock(\Magento\Quote\Api\Data\AddressInterface::class);
        $billingAddress->method('addData')->willReturnSelf();

        $shippingAddress = $this->createMock(\Magento\Quote\Api\Data\AddressInterface::class);
        $shippingAddress->method('addData')->willReturnSelf();
        $shippingAddress->method('setCollectShippingRates')->willReturnSelf();
        $shippingAddress->method('setShippingMethod')->willReturnSelf();

        $payment = $this->createMock(\Magento\Quote\Model\Quote\Payment::class);
        $payment->method('setMethod')->willReturnSelf();

        $quote = $this->createMock(CartInterface::class);
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

        $handler = $this->createHandler(
            cartManagement: $cartManagement,
            cartItemFactory: $cartItemFactory,
            cartItemRepository: $cartItemRepository,
            cartRepository: $cartRepository,
        );

        $handler->create([
            'customer_email' => 'test@test.com',
            'items' => [
                ['sku' => 'TEST-001', 'qty' => 2],
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

    private function createHandler(
        ?CartManagementInterface $cartManagement = null,
        ?CartRepositoryInterface $cartRepository = null,
        ?CartItemInterfaceFactory $cartItemFactory = null,
        ?CartItemRepositoryInterface $cartItemRepository = null,
        ?OrderRepositoryInterface $orderRepository = null,
        ?SearchCriteriaBuilder $searchCriteriaBuilder = null,
    ): OrderHandler {
        return new OrderHandler(
            $cartManagement ?? $this->createMock(CartManagementInterface::class),
            $cartRepository ?? $this->createMock(CartRepositoryInterface::class),
            $cartItemFactory ?? $this->createMock(CartItemInterfaceFactory::class),
            $cartItemRepository ?? $this->createMock(CartItemRepositoryInterface::class),
            $orderRepository ?? $this->createMock(OrderRepositoryInterface::class),
            $searchCriteriaBuilder ?? $this->createMock(SearchCriteriaBuilder::class),
        );
    }
}
