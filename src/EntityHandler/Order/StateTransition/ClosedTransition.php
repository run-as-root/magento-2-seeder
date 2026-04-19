<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler\Order\StateTransition;

use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
use RunAsRoot\Seeder\EntityHandler\Order\StateTransitionInterface;

class ClosedTransition implements StateTransitionInterface
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly TransactionFactory $transactionFactory,
        private readonly CreditmemoFactory $creditmemoFactory,
        private readonly CreditmemoManagementInterface $creditmemoManagement,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function getState(): string
    {
        return 'closed';
    }

    public function apply(OrderInterface $order, array $data): void
    {
        if (!method_exists($order, 'canInvoice') || !$order->canInvoice()) {
            return;
        }

        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
        $invoice->register();

        if (method_exists($order, 'setIsInProcess')) {
            $order->setIsInProcess(true);
        }

        $transaction = $this->transactionFactory->create();
        $transaction->addObject($invoice)->addObject($order)->save();

        $this->orderRepository->save($order);

        // Re-fetch so the creditmemo sees the post-invoice order state
        $freshOrder = $this->orderRepository->get((int) $order->getEntityId());

        $creditmemo = $this->creditmemoFactory->createByOrder($freshOrder);
        $this->creditmemoManagement->refund($creditmemo, true);

        // Magento observer chain may reset state during save; reassert explicitly.
        $freshOrder->setState('closed')->setStatus('closed');
        $this->orderRepository->save($freshOrder);
    }
}
