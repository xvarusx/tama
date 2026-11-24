<?php

declare(strict_types=1);

namespace App\DataFixtures\V1;

use App\Entity\Order;
use App\Entity\OrderLine;
use App\Enum\OrderStatus;
use App\Enum\OrderType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

/**
 * Seeds 30 orders (15 purchase + 15 sales) with 1-3 lines each.
 *
 * All state transitions follow the Order state machine. In particular a sales
 * order is always received before it is shipped (ship() rejects SUBMITTED).
 */
class OrdersFixtures extends Fixture
{
    /** @var array<int, array{name: string, sku: string, unitPrice: int}> */
    private const PRODUCTS = [
        ['name' => '27" 4K Monitor', 'sku' => 'MON-27', 'unitPrice' => 24900],
        ['name' => 'Wireless Mouse', 'sku' => 'MOU-01', 'unitPrice' => 2490],
        ['name' => 'Laptop Sleeve', 'sku' => 'SLE-01', 'unitPrice' => 1990],
        ['name' => 'Mechanical Keyboard', 'sku' => 'KEY-87', 'unitPrice' => 6900],
        ['name' => 'USB-C Cable', 'sku' => 'CAB-01', 'unitPrice' => 790],
        ['name' => 'Standing Desk', 'sku' => 'DSK-01', 'unitPrice' => 44900],
        ['name' => 'Webcam 1080p', 'sku' => 'CAM-01', 'unitPrice' => 8900],
        ['name' => 'Noise-Cancelling Headset', 'sku' => 'HDS-01', 'unitPrice' => 17900],
    ];

    /** @var array<int, array{id: string, name: string}> */
    private const SUPPLIERS = [
        ['id' => 'supreme-supplier-001', 'name' => 'Supreme Supplier Inc.'],
        ['id' => 'acme-supplier-002', 'name' => 'ACME Supplier'],
        ['id' => 'globex-supply-003', 'name' => 'Globex Supply Co.'],
        ['id' => 'initech-components-004', 'name' => 'Initech Components'],
        ['id' => 'soylent-wholesale-005', 'name' => 'Soylent Wholesale'],
        ['id' => 'umbrella-trading-006', 'name' => 'Umbrella Trading Ltd.'],
    ];

    /** @var array<int, array{id: string, name: string}> */
    private const CUSTOMERS = [
        ['id' => 'acme-corp-001', 'name' => 'ACME Corporation'],
        ['id' => 'globex-corp-001', 'name' => 'Globex Corporation'],
        ['id' => 'northwind-traders-001', 'name' => 'Northwind Traders'],
        ['id' => 'initech-001', 'name' => 'Initech LLC'],
        ['id' => 'soylent-corp-001', 'name' => 'Soylent Corp.'],
        ['id' => 'umbrella-retail-001', 'name' => 'Umbrella Retail'],
    ];

    /** @var array<int, OrderStatus> */
    private const PURCHASE_STATUSES = [
        OrderStatus::DRAFT,
        OrderStatus::SUBMITTED,
        OrderStatus::RECEIVED,
        OrderStatus::PARTIALLY_RECEIVED,
        OrderStatus::CANCELED,
    ];

    /** @var array<int, OrderStatus> */
    private const SALES_STATUSES = [
        OrderStatus::DRAFT,
        OrderStatus::SUBMITTED,
        OrderStatus::SHIPPED,
        OrderStatus::DELIVERED,
        OrderStatus::CANCELED,
    ];

    public function load(ObjectManager $manager): void
    {
        // 15 purchase orders: PO-0001 .. PO-0015
        for ($i = 0; $i < 15; ++$i) {
            $party = self::SUPPLIERS[$i % \count(self::SUPPLIERS)];
            $order = new Order(
                number: \sprintf('PO-%04d', $i + 1),
                type: OrderType::PURCHASE,
                partyId: $party['id'],
                partyName: $party['name'],
                currency: $i % 2 === 0 ? 'USD' : 'EUR',
            );

            $this->addLines($order, $i);
            $this->applyTransitions($order, self::PURCHASE_STATUSES[$i % \count(self::PURCHASE_STATUSES)]);

            $manager->persist($order);
        }

        // 15 sales orders: SO-0001 .. SO-0015
        for ($i = 0; $i < 15; ++$i) {
            $party = self::CUSTOMERS[$i % \count(self::CUSTOMERS)];
            $order = new Order(
                number: \sprintf('SO-%04d', $i + 1),
                type: OrderType::SALES,
                partyId: $party['id'],
                partyName: $party['name'],
                currency: $i % 2 === 0 ? 'USD' : 'EUR',
            );

            $this->addLines($order, $i + 15);
            $this->applyTransitions($order, self::SALES_STATUSES[$i % \count(self::SALES_STATUSES)]);

            $manager->persist($order);
        }

        // Lines and outbox events are cascaded from Order.
        $manager->flush();
    }

    /**
     * Adds 1-3 deterministic lines to the order.
     */
    private function addLines(Order $order, int $seed): void
    {
        $lineCount = 1 + $seed % 3;
        $productCount = \count(self::PRODUCTS);

        for ($lineIndex = 0; $lineIndex < $lineCount; ++$lineIndex) {
            $product = self::PRODUCTS[($seed + $lineIndex) % $productCount];

            $line = new OrderLine();
            $line->setProductId(Uuid::v7());
            $line->setProductName($product['name']);
            $line->setSku($product['sku']);
            $line->setQuantity(5 + ($seed * 7 + $lineIndex * 3) % 20);
            $line->setUnitPrice($product['unitPrice']); // minor units (cents)

            $order->addLine($line);
        }
    }

    /**
     * Drives the order to the requested legal status.
     *
     * The quantities arrays are keyed by ORDER LINE INDEX, because
     * Order::processLines() resolves each entry with $this->lines->get($key).
     */
    private function applyTransitions(Order $order, OrderStatus $targetStatus): void
    {
        switch ($targetStatus) {
            case OrderStatus::DRAFT:
                // Constructor already sets DRAFT; no transitions needed.
                return;

            case OrderStatus::SUBMITTED:
                $order->submit();
                return;

            case OrderStatus::RECEIVED:
                $order->submit();
                $order->receive($this->fullQuantities($order));
                return;

            case OrderStatus::PARTIALLY_RECEIVED:
                $order->submit();
                $order->receivePartial($this->partialQuantities($order));
                return;

            case OrderStatus::SHIPPED:
                $order->submit();
                $quantities = $this->fullQuantities($order);
                // A sales order must be received before it can be shipped.
                $order->receive($quantities);
                $order->ship($quantities);
                return;

            case OrderStatus::DELIVERED:
                $order->submit();
                $quantities = $this->fullQuantities($order);
                $order->receive($quantities);
                $order->ship($quantities);
                $order->markDelivered();
                return;

            case OrderStatus::CANCELED:
                $order->submit();
                $order->cancel();
                return;

            case OrderStatus::STOCK_REJECTED:
                $order->submit();
                $order->receive($this->fullQuantities($order));
                $order->rejectStock();
                return;
        }
    }

    /**
     * @return array<int, int> line index => full line quantity
     */
    private function fullQuantities(Order $order): array
    {
        $quantities = [];
        foreach ($order->getLines() as $lineIndex => $line) {
            $quantities[$lineIndex] = $line->getQuantity();
        }

        return $quantities;
    }

    /**
     * @return array<int, int> line index => partial line quantity
     */
    private function partialQuantities(Order $order): array
    {
        $quantities = [];
        foreach ($order->getLines() as $lineIndex => $line) {
            $quantities[$lineIndex] = \max(1, \intdiv($line->getQuantity(), 2));
        }

        return $quantities;
    }
}
