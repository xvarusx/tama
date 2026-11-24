<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\InvoiceReference;
use App\Entity\Payment;
use App\Enum\InstallmentStatus;
use App\Enum\PaymentDirection;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class PaymentsControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->client->disableReboot();

        $em = $this->em();
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function test_create_payment_endpoint(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/payments',
            content: json_encode([
                'number' => 'PYMT-TEST-001',
                'direction' => 'INCOMING',
                'method' => 'CASH',
                'partyId' => '11111111-1111-1111-1111-111111111111',
                'partyName' => 'Test Corp',
                'currency' => 'USD',
                'amount' => 50000,
                'reference' => 'Test payment',
            ]),
            server: ['CONTENT_TYPE' => 'application/json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('success', $data['status']);
        $this->assertSame('PYMT-TEST-001', $data['data']['attributes']['number']);
        $this->assertSame('INCOMING', $data['data']['attributes']['direction']);
        $this->assertSame('CASH', $data['data']['attributes']['method']);
        $this->assertSame('PENDING', $data['data']['attributes']['status']);
        $this->assertSame(50000, $data['data']['attributes']['amount']);

        // Verify Location header
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('/api/v1/payments/', $location);
    }

    public function test_create_payment_validation_fails(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/payments',
            content: json_encode([
                'number' => '', // Required but empty
                'direction' => 'INCOMING',
                'method' => 'CASH',
                'partyId' => '11111111-1111-1111-1111-111111111111',
                'partyName' => 'Test Corp',
                'currency' => 'USD',
                'amount' => -100, // Must be positive
            ]),
            server: ['CONTENT_TYPE' => 'application/json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_show_payment(): void
    {
        $em = $this->em();

        // First create a payment
        $payment = new Payment(
            'PYMT-SHOW-001',
            PaymentDirection::INCOMING,
            PaymentMethod::BANK_TRANSFER,
            '11111111-1111-1111-1111-111111111111',
            'Test Corp',
            'USD',
            100000
        );
        $payment->confirm();
        $em->persist($payment);
        $em->flush();

        $this->client->request('GET', '/api/v1/payments/' . $payment->getId()->toString());

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('success', $data['status']);
        $this->assertSame('PYMT-SHOW-001', $data['data']['attributes']['number']);
        $this->assertSame('COMPLETED', $data['data']['attributes']['status']);
    }

    public function test_show_non_existent_payment(): void
    {
        $this->client->request('GET', '/api/v1/payments/aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function test_record_payment_transitions_status(): void
    {
        $em = $this->em();

        $payment = new Payment(
            'PYMT-RECORD-001',
            PaymentDirection::INCOMING,
            PaymentMethod::CHEQUE,
            '11111111-1111-1111-1111-111111111111',
            'Test Corp',
            'USD',
            50000
        );
        $em->persist($payment);
        $em->flush();

        $this->client->request('POST', '/api/v1/payments/' . $payment->getId()->toString() . '/record');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('COMPLETED', $data['data']['attributes']['status']);

        // Verify in database
        $updatedPayment = $em->find(Payment::class, $payment->getId());
        $this->assertSame(PaymentStatus::COMPLETED, $updatedPayment->getStatus());
    }

    public function test_record_completed_payment_fails(): void
    {
        $em = $this->em();

        $payment = new Payment(
            'PYMT-COMPLETE-001',
            PaymentDirection::INCOMING,
            PaymentMethod::CASH,
            '11111111-1111-1111-1111-111111111111',
            'Test Corp',
            'USD',
            50000
        );
        $payment->confirm();
        $em->persist($payment);
        $em->flush();

        $this->client->request('POST', '/api/v1/payments/' . $payment->getId()->toString() . '/record');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('INVALID_STATE', $data['error']['code']);
    }

    public function test_fail_payment_transitions_status(): void
    {
        $em = $this->em();

        $payment = new Payment(
            'PYMT-FAIL-001',
            PaymentDirection::INCOMING,
            PaymentMethod::CHEQUE,
            '11111111-1111-1111-1111-111111111111',
            'Test Corp',
            'USD',
            50000
        );
        $em->persist($payment);
        $em->flush();

        $this->client->request('POST', '/api/v1/payments/' . $payment->getId()->toString() . '/fail');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('FAILED', $data['data']['attributes']['status']);
    }

    public function test_reverse_completed_payment(): void
    {
        $em = $this->em();

        $payment = new Payment(
            'PYMT-REVERSE-001',
            PaymentDirection::INCOMING,
            PaymentMethod::CASH,
            '11111111-1111-1111-1111-111111111111',
            'Test Corp',
            'USD',
            50000
        );
        $payment->confirm();
        $em->persist($payment);
        $em->flush();

        $this->client->request('POST', '/api/v1/payments/' . $payment->getId()->toString() . '/reverse');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('REVERSED', $data['data']['attributes']['status']);
    }

    public function test_list_payments(): void
    {
        $em = $this->em();

        // Create a few payments
        for ($i = 1; $i <= 3; $i++) {
            $payment = new Payment(
                'PYMT-LIST-' . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
                PaymentDirection::INCOMING,
                PaymentMethod::CASH,
                '11111111-1111-1111-1111-111111111111',
                'Test Corp',
                'USD',
                10000 * $i
            );
            if ($i % 2 === 0) {
                $payment->confirm();
            }
            $em->persist($payment);
        }
        $em->flush();

        $this->client->request('GET', '/api/v1/payments?page=1&pageSize=10');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('success', $data['status']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
    }

    public function test_list_payments_filtered_by_status(): void
    {
        $em = $this->em();

        // Create payments with different statuses
        $pending = new Payment(
            'PYMT-FILT-PEND',
            PaymentDirection::INCOMING,
            PaymentMethod::CASH,
            '11111111-1111-1111-1111-111111111111',
            'Test Corp',
            'USD',
            10000
        );

        $completed = new Payment(
            'PYMT-FILT-COMPLETE',
            PaymentDirection::INCOMING,
            PaymentMethod::CASH,
            '11111111-1111-1111-1111-111111111111',
            'Test Corp',
            'USD',
            10000
        );
        $completed->confirm();

        $em->persist($pending);
        $em->persist($completed);
        $em->flush();

        $this->client->request('GET', '/api/v1/payments?status=COMPLETED');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        foreach ($data['data'] as $payment) {
            $this->assertSame('COMPLETED', $payment['attributes']['status']);
        }
    }

    public function test_cancel_payment(): void
    {
        $em = $this->em();

        $payment = new Payment(
            'PYMT-CANCEL-001',
            PaymentDirection::INCOMING,
            PaymentMethod::CHEQUE,
            '11111111-1111-1111-1111-111111111111',
            'Test Corp',
            'USD',
            50000
        );
        $em->persist($payment);
        $em->flush();

        $this->client->request('DELETE', '/api/v1/payments/' . $payment->getId()->toString() . '/cancel');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('CANCELED', $data['data']['attributes']['status']);
    }

    private function em(): EntityManagerInterface
    {
        return $this->client->getContainer()->get('doctrine')->getManager();
    }
}