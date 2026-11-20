<?php

declare(strict_types=1);

namespace App\Tests\Functional\Handler;

use App\Api\V1\Request\CreatePaymentRequest;
use App\Entity\OutboxEvent;
use App\Entity\Payment;
use App\Enum\PaymentDirection;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Handler\Payment\CreatePaymentHandler;
use App\Repository\OutboxEventRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CreatePaymentHandlerTest extends KernelTestCase
{
    private EntityManager $entityManager;
    private CreatePaymentHandler $handler;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()
            ->get('doctrine')
            ->getManager();

        $this->handler = self::getContainer()->get(CreatePaymentHandler::class);
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
        parent::tearDown();
    }

    public function test_create_payment_persists_and_creates_outbox_event(): void
    {
        $request = new CreatePaymentRequest(
            number: 'PYMT-TEST-001',
            direction: PaymentDirection::INCOMING,
            method: PaymentMethod::CASH,
            partyId: '11111111-1111-1111-1111-111111111111',
            partyName: 'Test Corp',
            currency: 'USD',
            amount: 50000,
            reference: 'Test payment'
        );

        $payment = ($this->handler)($request);

        // Verify payment is persisted
        $this->assertNotNull($payment->getId());
        $this->assertSame('PYMT-TEST-001', $payment->getNumber());
        $this->assertSame(PaymentDirection::INCOMING, $payment->getDirection());
        $this->assertSame(PaymentMethod::CASH, $payment->getMethod());
        $this->assertSame(PaymentStatus::PENDING, $payment->getStatus());
        $this->assertSame('USD', $payment->getCurrency());
        $this->assertSame(50000, $payment->getAmount());
        $this->assertSame('Test payment', $payment->getReference());

        // Verify outbox event was created
        $outboxRepo = $this->entityManager->getRepository(OutboxEvent::class);
        $outboxEvents = $outboxRepo->findBy(['eventType' => 'payment.created']);
        $this->assertCount(1, $outboxEvents);

        $payload = $outboxEvents[0]->getPayload();
        $this->assertSame($payment->getId()->toString(), $payload['paymentId']);
        $this->assertSame('PYMT-TEST-001', $payload['number']);
    }

    public function test_create_payment_with_bank_transfer(): void
    {
        $request = new CreatePaymentRequest(
            number: 'PYMT-TEST-002',
            direction: PaymentDirection::INCOMING,
            method: PaymentMethod::BANK_TRANSFER,
            partyId: '22222222-2222-2222-2222-222222222222',
            partyName: 'Another Corp',
            currency: 'EUR',
            amount: 200000
        );

        $payment = ($this->handler)($request);

        $this->assertSame(PaymentMethod::BANK_TRANSFER, $payment->getMethod());
        $this->assertSame('EUR', $payment->getCurrency());
        $this->assertSame(200000, $payment->getAmount());
    }

    public function test_create_payment_with_cheque(): void
    {
        $request = new CreatePaymentRequest(
            number: 'PYMT-TEST-003',
            direction: PaymentDirection::INCOMING,
            method: PaymentMethod::CHEQUE,
            partyId: '33333333-3333-3333-3333-333333333333',
            partyName: 'Third Corp',
            currency: 'USD',
            amount: 100000
        );

        $payment = ($this->handler)($request);

        $this->assertSame(PaymentMethod::CHEQUE, $payment->getMethod());
        $this->assertSame(PaymentStatus::PENDING, $payment->getStatus());
    }

    public function test_create_payment_with_outgoing_direction(): void
    {
        $request = new CreatePaymentRequest(
            number: 'PYMT-TEST-OUT-001',
            direction: PaymentDirection::OUTGOING,
            method: PaymentMethod::OTHER,
            partyId: '44444444-4444-4444-4444-444444444444',
            partyName: 'Vendor Corp',
            currency: 'USD',
            amount: 75000
        );

        $payment = ($this->handler)($request);

        $this->assertSame(PaymentDirection::OUTGOING, $payment->getDirection());
    }

    public function test_create_payment_generates_unique_number(): void
    {
        $request1 = new CreatePaymentRequest(
            number: 'PYMT-UNIQUE-001',
            direction: PaymentDirection::INCOMING,
            method: PaymentMethod::CASH,
            partyId: '11111111-1111-1111-1111-111111111111',
            partyName: 'Test Corp',
            currency: 'USD',
            amount: 10000
        );

        $request2 = new CreatePaymentRequest(
            number: 'PYMT-UNIQUE-001', // Same number - should fail due to unique constraint
            direction: PaymentDirection::INCOMING,
            method: PaymentMethod::CASH,
            partyId: '22222222-2222-2222-2222-222222222222',
            partyName: 'Another Corp',
            currency: 'USD',
            amount: 20000
        );

        ($this->handler)($request1);

        // Second request with same number should throw
        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);
        ($this->handler)($request2);
    }
}