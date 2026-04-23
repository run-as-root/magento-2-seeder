<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler\Order\StateTransition;

use Magento\Framework\DB\Transaction;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\EntityHandler\Order\StateTransition\ClosedTransition;

final class ClosedTransitionTest extends TestCase
{
    public function test_get_state_returns_closed(): void
    {
        $invoiceService = $this->createMock(InvoiceService::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);
        $creditmemoFactory = $this->createMock(CreditmemoFactory::class);
        $creditmemoManagement = $this->createMock(CreditmemoManagementInterface::class);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);

        $transition = new ClosedTransition(
            $invoiceService,
            $transactionFactory,
            $creditmemoFactory,
            $creditmemoManagement,
            $orderRepository
        );

        $this->assertSame('closed', $transition->getState());
    }

    public function test_apply_invoices_then_creates_creditmemo_and_refunds_offline(): void
    {
        $invoiceService = $this->createMock(InvoiceService::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);
        $creditmemoFactory = $this->createMock(CreditmemoFactory::class);
        $creditmemoManagement = $this->createMock(CreditmemoManagementInterface::class);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['canInvoice', 'getAllItems', 'getEntityId', 'getIncrementId', 'getState', 'hold', 'cancel', 'setState', 'setStatus'])
            ->addMethods(['setIsInProcess'])
            ->getMock();
        $refreshedOrder = $this->createMock(Order::class);
        $invoice = $this->createMock(Invoice::class);
        $transaction = $this->createMock(Transaction::class);
        $creditmemo = $this->createMock(Creditmemo::class);

        $order->expects($this->once())->method('canInvoice')->willReturn(true);
        $order->expects($this->atLeastOnce())
            ->method('setIsInProcess')
            ->with(true)
            ->willReturnSelf();
        $order->method('getEntityId')->willReturn('4242');

        $refreshedOrder->expects($this->once())
            ->method('setState')
            ->with('closed')
            ->willReturnSelf();
        $refreshedOrder->expects($this->once())
            ->method('setStatus')
            ->with('closed')
            ->willReturnSelf();

        $invoiceService->expects($this->once())
            ->method('prepareInvoice')
            ->with($order)
            ->willReturn($invoice);

        $invoice->expects($this->once())
            ->method('setRequestedCaptureCase')
            ->with('offline')
            ->willReturnSelf();
        $invoice->expects($this->once())->method('register')->willReturnSelf();

        $transactionFactory->expects($this->once())->method('create')->willReturn($transaction);

        $transaction->expects($this->exactly(2))
            ->method('addObject')
            ->willReturnSelf();
        $transaction->expects($this->once())->method('save')->willReturnSelf();

        $orderRepository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function ($saved) use ($order, $refreshedOrder) {
                static $calls = 0;
                $calls++;
                if ($calls === 1) {
                    $this->assertSame($order, $saved);
                } else {
                    $this->assertSame($refreshedOrder, $saved);
                }
                return $saved;
            });
        $orderRepository->expects($this->once())
            ->method('get')
            ->with(4242)
            ->willReturn($refreshedOrder);

        $creditmemoFactory->expects($this->once())
            ->method('createByOrder')
            ->with($refreshedOrder)
            ->willReturn($creditmemo);

        $creditmemoManagement->expects($this->once())
            ->method('refund')
            ->with($creditmemo, true)
            ->willReturn($creditmemo);

        $transition = new ClosedTransition(
            $invoiceService,
            $transactionFactory,
            $creditmemoFactory,
            $creditmemoManagement,
            $orderRepository
        );
        $transition->apply($order, []);
    }

    public function test_apply_skips_when_order_cannot_be_invoiced(): void
    {
        $invoiceService = $this->createMock(InvoiceService::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);
        $creditmemoFactory = $this->createMock(CreditmemoFactory::class);
        $creditmemoManagement = $this->createMock(CreditmemoManagementInterface::class);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $order = $this->createMock(Order::class);

        $order->expects($this->once())->method('canInvoice')->willReturn(false);

        $invoiceService->expects($this->never())->method('prepareInvoice');
        $transactionFactory->expects($this->never())->method('create');
        $creditmemoFactory->expects($this->never())->method('createByOrder');
        $creditmemoManagement->expects($this->never())->method('refund');
        $orderRepository->expects($this->never())->method('save');

        $transition = new ClosedTransition(
            $invoiceService,
            $transactionFactory,
            $creditmemoFactory,
            $creditmemoManagement,
            $orderRepository
        );
        $transition->apply($order, []);
    }
}
