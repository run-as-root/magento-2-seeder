<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler\Order\StateTransition;

use Magento\Framework\DB\Transaction;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\EntityHandler\Order\StateTransition\ProcessingTransition;

final class ProcessingTransitionTest extends TestCase
{
    public function test_get_state_returns_processing(): void
    {
        $invoiceService = $this->createMock(InvoiceService::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);

        $transition = new ProcessingTransition($invoiceService, $transactionFactory, $orderRepository);

        $this->assertSame('processing', $transition->getState());
    }

    public function test_apply_prepares_invoice_registers_and_saves_via_transaction(): void
    {
        $invoiceService = $this->createMock(InvoiceService::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $order = $this->createMock(Order::class);
        $invoice = $this->createMock(Invoice::class);
        $transaction = $this->createMock(Transaction::class);

        $order->expects($this->once())->method('canInvoice')->willReturn(true);
        $order->expects($this->atLeastOnce())
            ->method('setIsInProcess')
            ->with(true)
            ->willReturnSelf();
        $order->expects($this->once())
            ->method('setState')
            ->with('processing')
            ->willReturnSelf();
        $order->expects($this->once())
            ->method('setStatus')
            ->with('processing')
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
            ->with($order)
            ->willReturn($order);

        $transition = new ProcessingTransition($invoiceService, $transactionFactory, $orderRepository);
        $transition->apply($order, []);
    }

    public function test_apply_skips_when_order_cannot_be_invoiced(): void
    {
        $invoiceService = $this->createMock(InvoiceService::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $order = $this->createMock(Order::class);

        $order->expects($this->once())->method('canInvoice')->willReturn(false);

        $invoiceService->expects($this->never())->method('prepareInvoice');
        $transactionFactory->expects($this->never())->method('create');
        $orderRepository->expects($this->never())->method('save');

        $transition = new ProcessingTransition($invoiceService, $transactionFactory, $orderRepository);
        $transition->apply($order, []);
    }
}
