<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: 'App\Repository\PaymentAllocationRepository')]
#[ORM\Index(name: 'idx_allocation_installment', columns: ['installment_id'])]
#[ORM\Index(name: 'idx_allocation_invoice', columns: ['invoice_id'])]
#[ORM\Index(name: 'idx_allocation_payment', columns: ['payment_id'])]
class PaymentAllocation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Payment::class, inversedBy: 'allocations')]
    #[ORM\JoinColumn(nullable: false)]
    private Payment $payment;

    #[ORM\ManyToOne(targetEntity: Installment::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Installment $installment;

    #[ORM\ManyToOne(targetEntity: InvoiceReference::class)]
    #[ORM\JoinColumn(nullable: false)]
    private InvoiceReference $invoice;

    #[ORM\Column(type: Types::INTEGER)]
    private int $amount;

    public function __construct(Payment $payment, Installment $installment, InvoiceReference $invoice, int $amount)
    {
        $this->id = Uuid::v7();
        $this->payment = $payment;
        $this->installment = $installment;
        $this->invoice = $invoice;
        $this->amount = $amount;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    public function getInstallment(): Installment
    {
        return $this->installment;
    }

    public function getInvoice(): InvoiceReference
    {
        return $this->invoice;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }
}