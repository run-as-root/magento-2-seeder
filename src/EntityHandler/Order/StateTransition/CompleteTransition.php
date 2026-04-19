<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler\Order\StateTransition;

use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\ShipmentFactory;
use Magento\Sales\Model\Service\InvoiceService;
use RunAsRoot\Seeder\EntityHandler\Order\StateTransitionInterface;

class CompleteTransition implements StateTransitionInterface
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly ShipmentFactory $shipmentFactory,
        private readonly TransactionFactory $transactionFactory,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function getState(): string
    {
        return 'complete';
    }

    public function apply(OrderInterface $order, array $data): void
    {
        if (!method_exists($order, 'canInvoice') || !$order->canInvoice()) {
            return;
        }

        // 1. Invoice (offline capture)
        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
        $invoice->register();

        if (method_exists($order, 'setIsInProcess')) {
            $order->setIsInProcess(true);
        }

        // 2. Shipment for all items, full qty
        $itemsQty = $this->buildItemsQtyMap($order);
        $shipment = $this->shipmentFactory->create($order, $itemsQty);
        $shipment->register();

        // 3. Commit invoice + shipment + order atomically
        $transaction = $this->transactionFactory->create();
        $transaction
            ->addObject($invoice)
            ->addObject($shipment)
            ->addObject($order)
            ->save();

        $this->orderRepository->save($order);

        // Magento observer chain may reset state during save; reassert explicitly.
        $order->setState('complete')->setStatus('complete');
        $this->orderRepository->save($order);
    }

    /**
     * @return array<int, float> map of order_item_id => qty
     */
    private function buildItemsQtyMap(OrderInterface $order): array
    {
        $map = [];
        if (!method_exists($order, 'getAllItems')) {
            return $map;
        }
        foreach ($order->getAllItems() as $item) {
            $map[(int) $item->getItemId()] = (float) $item->getQtyOrdered();
        }
        return $map;
    }
}
