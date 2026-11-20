<?php

declare(strict_types=1);

namespace App\Tests\Functional\Handler;

use App\Api\V1\Request\CreatePaymentPlanRequest;
use App\Api\V1\Request\InstallmentRequest;
use App\Entity\InvoiceReference;
use App\Entity\OutboxEvent;
use App\Entity\PaymentPlan;
use App\Handler\PaymentPlan\CreatePaymentPlanHandler;
use App\Repository\OutboxEventRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CreatePaymentPlanHandlerTest extends KernelTestCase
{
    private EntityManager $entityManager;
    private CreatePaymentPlanHandler $handler;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()
            ->get('doctrine')
            ->getManager();

        $this->handler = self::getContainer()->get(CreatePaymentPlanHandler::class);
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
        parent::tearDown();
    }

    public function test_create_payment_plan_creates_plan_and_installments(): void
    {
        $request = new CreatePaymentPlanRequest(
            invoiceId: '11111111-1111-1111-1111-111111111111',
            totalAmount: 100000, // 1000.00
            currency: 'USD',
            installments: [
                new InstallmentRequest('2026-11-15', 30000),  // 300.00
                new InstallmentRequest('2026-11-20', 30000),  // 300.00
                new InstallmentRequest('2026-11-25', 40000),   // 400.00
            ]
        );

        $paymentPlan = ($this->handler)($request);

        // Verify payment plan is persisted
        $this->assertNotNull($paymentPlan->getId());
        $this->assertSame('11111111-1111-1111-1111-111111111111', $paymentPlan->getInvoiceId());
        $this->assertSame(100000, $paymentPlan->getTotalAmount());
        $this->assertSame('USD', $paymentPlan->getCurrency());
        $this->assertSame('ACTIVE', $paymentPlan->getStatus());

        // Verify installments were created
        $installments = iterator_to_array($paymentPlan->getInstallments());
        $this->assertCount(3, $installments);

        // Verify installment details
        $this->assertSame(1, $installments[0]->getSequence());
        $this->assertSame('2026-11-15', $installments[0]->getDueDate()->format('Y-m-d'));
        $this->assertSame(30000, $installments[0]->getAmount());

        $this->assertSame(2, $installments[1]->getSequence());
        $this->assertSame(30000, $installments[1]->getAmount());

        $this->assertSame(3, $installments[2]->getSequence());
        $this->assertSame(40000, $installments[2]->getAmount());

        // Verify invoice reference was created
        $invoiceRefRepo = $this->entityManager->getRepository(InvoiceReference::class);
        $invoiceRef = $invoiceRefRepo->findOneBy(['invoiceId' => '11111111-1111-1111-1111-111111111111']);
        $this->assertNotNull($invoiceRef);
        $this->assertSame('11111111-1111-1111-1111-111111111111', $invoiceRef->getInvoiceId());
    }

    public function test_create_payment_plan_with_existing_invoice_reference(): void
    {
        // First create an invoice reference
        $existingInvoiceRef = new InvoiceReference(
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'INV-999',
            'SALE',
            'party-123456',
            'Existing Customer',
            'EUR',
            500000,
            500000,
            'ISSUED'
        );
        $this->entityManager->persist($existingInvoiceRef);
        $this->entityManager->flush();

        // Now create a payment plan referencing this invoice
        $request = new CreatePaymentPlanRequest(
            invoiceId: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            totalAmount: 500000,
            currency: 'EUR',
            installments: [
                new InstallmentRequest('2026-12-01', 100000),
            ]
        );

        $paymentPlan = ($this->handler)($request);

        $this->assertNotNull($paymentPlan->getId());
        $this->assertSame('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', $paymentPlan->getInvoiceId());
    }

    public function test_create_payment_plan_with_multiple_installments(): void
    {
        $request = new CreatePaymentPlanRequest(
            invoiceId: '22222222-2222-2222-2222-222222222222',
            totalAmount: 1200000, // 12000.00
            currency: 'EUR',
            installments: [
                new InstallmentRequest('2026-12-01', 200000),
                new InstallmentRequest('2026-12-15', 200000),
                new InstallmentRequest('2026-12-29', 200000),
                new InstallmentRequest('2026-01-12', 200000),
                new InstallmentRequest('2026-01-26', 200000),
                new InstallmentRequest('2026-02-09', 200000),
            ]
        );

        $paymentPlan = ($this->handler)($request);

        $installments = iterator_to_array($paymentPlan->getInstallments());
        $this->assertCount(6, $installments);

        // Verify sequence numbers
        foreach ($installments as $index => $installment) {
            $this->assertSame($index + 1, $installment->getSequence());
        }
    }

    public function test_create_payment_plan_uses_provided_currency(): void
    {
        $request = new CreatePaymentPlanRequest(
            invoiceId: '33333333-3333-3333-3333-333333333333',
            totalAmount: 25000,
            currency: 'GBP',
            installments: [
                new InstallmentRequest('2026-11-15', 25000),
            ]
        );

        $paymentPlan = ($this->handler)($request);

        $this->assertSame('GBP', $paymentPlan->getCurrency());
    }
}