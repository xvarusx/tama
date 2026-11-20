<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrderStatus;
use App\Enum\OrderType;
use App\Event\OrderSubmittedV1;
use App\Event\OrderCanceledV1;
use App\Event\OrderShippedV1;
use App\Event\OrderDeliveredV1;
use App\Event\PurchaseOrderReceivedV1;
use App\Exceptions\DomainException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: 'App\Repository\OrderRepository')]
#[ORM\Table(name: 'orders')]
#[ORM\Index(name: 'idx_order_number', columns: ['number'])]
#[ORM\Index(name: 'idx_order_status', columns: ['status'])]
class Order
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 50)]
    private string $number;

    #[ORM\Column(enumType: OrderType::class)]
    private OrderType $type;

    #[ORM\Column(enumType: OrderStatus::class)]
    private OrderStatus $status;

    #[ORM\Column(length: 36)]
    private string $partyId;

    #[ORM\Column(length: 255)]
    private string $partyName;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $version = 1;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $totalAmount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $totalProcessed = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\OneToMany(targetEntity: OrderLine::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lines;

    #[ORM\OneToMany(targetEntity: OutboxEvent::class, mappedBy: 'order', cascade: ['persist'], orphanRemoval: true)]
    private Collection $outboxEvents;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $invoiceId = null;

    public function __construct(
        string $number,
        OrderType $type,
        string $partyId,
        string $partyName,
        string $currency
    ) {
        $this->id = Uuid::v7();
        $this->number = $number;
        $this->type = $type;
        $this->status = OrderStatus::DRAFT;
        $this->partyId = $partyId;
        $this->partyName = $partyName;
        $this->currency = $currency;
        $this->createdAt = new \DateTimeImmutable();
        $this->lines = new ArrayCollection();
        $this->outboxEvents = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    /** Alias kept so existing v1 event payloads ($order->getUuid()) keep working. */
    public function getUuid(): Uuid
    {
        return $this->id;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getType(): OrderType
    {
        return $this->type;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getPartyId(): string
    {
        return $this->partyId;
    }

    public function getPartyName(): string
    {
        return $this->partyName;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getTotalAmount(): int
    {
        return $this->totalAmount;
    }

    public function getTotalProcessed(): int
    {
        return $this->totalProcessed;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function getInvoiceId(): ?Uuid
    {
        return $this->invoiceId;
    }

    /**
     * @return Collection<int, OrderLine>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    /**
     * @return Collection<int, OutboxEvent>
     */
    public function getOutboxEvents(): Collection
    {
        return $this->outboxEvents;
    }

    public function addLine(OrderLine $line): self
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setOrder($this);
            $this->recalculateTotals();
        }
        return $this;
    }

    public function removeLine(OrderLine $line): self
    {
        if ($this->lines->removeElement($line)) {
            $this->recalculateTotals();
        }
        return $this;
    }

    public function removeAllLines(): self
    {
        foreach ($this->lines as $line) {
            $line->setOrder(null);
        }
        $this->lines->clear();
        $this->recalculateTotals();
        return $this;
    }

    public function attachInvoice(Uuid $invoiceId): void
    {
        $this->invoiceId = $invoiceId;
    }

    public function submit(): void
    {
        if ($this->status !== OrderStatus::DRAFT) {
            throw new DomainException('Only draft orders can be submitted.');
        }

        if (empty($this->lines->getValues())) {
            throw new DomainException('An order cannot be submitted without lines.');
        }

        $this->status = OrderStatus::SUBMITTED;
        $this->submittedAt = new \DateTimeImmutable();
        $this->addOutboxEvent(OrderSubmittedV1::create($this));
    }

    public function receive(array $quantities): void
    {
        if (!in_array($this->status, [OrderStatus::SUBMITTED, OrderStatus::RECEIVED, OrderStatus::PARTIALLY_RECEIVED], true)) {
            throw new DomainException('Only submitted or received orders can receive goods.');
        }

        $this->processLines($quantities, 'receive');
        $this->status = OrderStatus::RECEIVED;
        $this->addOutboxEvent(PurchaseOrderReceivedV1::create($this, $quantities));
    }

    public function receivePartial(array $quantities): void
    {
        if (!in_array($this->status, [OrderStatus::SUBMITTED, OrderStatus::RECEIVED, OrderStatus::PARTIALLY_RECEIVED], true)) {
            throw new DomainException('Only submitted or received orders can partially receive goods.');
        }

        $this->processLines($quantities, 'receive');
        $this->status = OrderStatus::PARTIALLY_RECEIVED;
        $this->addOutboxEvent(PurchaseOrderReceivedV1::create($this, $quantities));
    }

    public function ship(array $quantities): void
    {
        if ($this->type !== OrderType::SALES) {
            throw new DomainException('Only sales orders can be shipped.');
        }

        if (!in_array($this->status, [OrderStatus::RECEIVED, OrderStatus::PARTIALLY_RECEIVED], true)) {
            throw new DomainException('Only received sales orders can be shipped.');
        }

        $this->processLines($quantities, 'ship');
        $this->status = OrderStatus::SHIPPED;
        $this->addOutboxEvent(OrderShippedV1::create($this, $quantities));
    }

    public function shipPartial(array $quantities): void
    {
        if ($this->type !== OrderType::SALES) {
            throw new DomainException('Only sales orders can be shipped.');
        }

        if (!in_array($this->status, [OrderStatus::RECEIVED, OrderStatus::PARTIALLY_RECEIVED, OrderStatus::SHIPPED], true)) {
            throw new DomainException('Only received or shipped sales orders can partial ship.');
        }

        $this->processLines($quantities, 'ship');
        $this->status = OrderStatus::PARTIALLY_RECEIVED;
        $this->addOutboxEvent(OrderShippedV1::create($this, $quantities));
    }

    public function markDelivered(): void
    {
        if ($this->type !== OrderType::SALES) {
            throw new DomainException('Only sales orders can be delivered.');
        }

        if ($this->status !== OrderStatus::SHIPPED) {
            throw new DomainException('Only shipped sales orders can be delivered.');
        }

        $this->status = OrderStatus::DELIVERED;
        $this->addOutboxEvent(OrderDeliveredV1::create($this));
    }

    public function rejectStock(): void
    {
        if (!in_array($this->status, [OrderStatus::RECEIVED, OrderStatus::PARTIALLY_RECEIVED], true)) {
            throw new DomainException('Stock can only be rejected for received orders.');
        }

        $this->status = OrderStatus::STOCK_REJECTED;
    }

    public function cancel(): void
    {
        if ($this->status === OrderStatus::CANCELED) {
            throw new DomainException('Order is already canceled.');
        }

        $previousStatus = $this->status;
        $this->status = OrderStatus::CANCELED;
        $this->addOutboxEvent(OrderCanceledV1::create($this, $previousStatus));
    }

    private function recalculateTotals(): void
    {
        $this->totalAmount = 0;
        $this->totalProcessed = 0;

        foreach ($this->lines as $line) {
            $this->totalAmount += $line->getSubtotal();
            $this->totalProcessed += $line->getProcessedQuantity() * $line->getUnitPrice();
        }
    }

    private function processLines(array $quantities, string $operation): void
    {
        foreach ($quantities as $lineId => $quantity) {
            $line = $this->lines->get($lineId);
            if ($line) {
                $method = $operation === 'receive' ? 'process' : 'changeQuantity';
                $line->{$method}($quantity);
            }
        }
    }

    private function addOutboxEvent(object $event): void
    {
        if (method_exists($event, 'toOutbox')) {
            $outboxEvent = $event->toOutbox($this);
            $this->outboxEvents->add($outboxEvent);
        }
    }

    public function pullEvents(): array
    {
        $events = [];
        foreach ($this->outboxEvents as $event) {
            $events[] = $event;
        }
        return $events;
    }
}