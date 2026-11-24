<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1;

use App\Entity\Delivery;
use App\Entity\DeliveryAttempt;
use App\Entity\Notification;
use App\Enum\Channel;
use App\Enum\DeliveryStatus;
use App\Enum\Severity;
use App\Security\Caller;
use App\Tests\Support\FakeAuthServiceClient;
use App\Utils\DemoUserUuids;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class AdminDeliveriesControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private FakeAuthServiceClient $auth;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $container = $this->client->getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->em->getConfiguration()->setUseSchemaEditorApi(true);
        new SchemaTool($this->em)->createSchema($this->em->getMetadataFactory()->getAllMetadata());

        $this->auth = $container->get(FakeAuthServiceClient::class);
        $this->asAdmin();
    }

    public function testRejectsNonAdmin(): void
    {
        $this->auth->setCaller(new Caller(DemoUserUuids::forNumber(1), 'jane@example.com', 'Jane Doe', ['ROLE_USER']));

        $this->get('/api/v1/admin/deliveries');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('FORBIDDEN', $this->json()['error']['code']);
    }

    public function testListsDeliveriesWithFilters(): void
    {
        $this->seedDelivery('jane@example.com', DeliveryStatus::FAILED, 'invoice.issued');
        $this->seedDelivery('john@example.com', DeliveryStatus::SENT, 'payment.installment.overdue');

        $this->get('/api/v1/admin/deliveries?status=failed&channel=email&type=invoice.issued&search=jane');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $this->json();
        self::assertSame('success', $body['status']);
        self::assertCount(1, $body['data']);
        self::assertSame('jane@example.com', $body['data'][0]['attributes']['recipientEmail']);
        self::assertSame('failed', $body['data'][0]['attributes']['status']);
        self::assertSame('invoice.issued', $body['data'][0]['attributes']['notification']['type']);
        self::assertArrayNotHasKey('body', $body['data'][0]['attributes']);
        self::assertSame(1, $body['pagination']['totalItems']);
    }

    public function testShowIncludesAttempts(): void
    {
        $delivery = $this->seedDelivery('jane@example.com', DeliveryStatus::FAILED, 'invoice.issued');
        $this->em->persist(new DeliveryAttempt($delivery, 1, null, 'SMTP temporarily unavailable'));
        $this->em->flush();

        $this->get('/api/v1/admin/deliveries/' . (string)$delivery->getId());

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $attributes = $this->json()['data']['attributes'];
        self::assertSame('Rendered email body', $attributes['body']);
        self::assertSame(1, $attributes['attempts']);
        self::assertCount(1, $attributes['attemptHistory']);
        self::assertSame(1, $attributes['attemptHistory'][0]['attemptNumber']);
        self::assertSame('SMTP temporarily unavailable', $attributes['attemptHistory'][0]['error']);
        self::assertNull($attributes['attemptHistory'][0]['providerMessageId']);
        self::assertArrayHasKey('attemptedAt', $attributes['attemptHistory'][0]);
    }

    public function testRequeueFailedDeliveryResetsState(): void
    {
        $delivery = $this->seedDelivery('jane@example.com', DeliveryStatus::FAILED, 'invoice.issued');
        $this->em->persist(new DeliveryAttempt($delivery, 1, null, 'Permanent failure'));
        $this->em->flush();

        $this->post('/api/v1/admin/deliveries/' . (string)$delivery->getId() . '/requeue');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $attributes = $this->json()['data']['attributes'];
        self::assertSame('queued', $attributes['status']);
        self::assertSame(0, $attributes['attempts']);
        self::assertNull($attributes['lastError']);
        self::assertNotNull($attributes['nextAttemptAt']);
        self::assertCount(1, $attributes['attemptHistory']);
    }

    public function testRequeueSentOrQueuedReturns409(): void
    {
        $sent = $this->seedDelivery('jane@example.com', DeliveryStatus::SENT, 'invoice.issued');
        $queued = $this->seedDelivery('john@example.com', DeliveryStatus::QUEUED, 'invoice.issued');

        $this->post('/api/v1/admin/deliveries/' . (string)$sent->getId() . '/requeue');
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('INVALID_DELIVERY_TRANSITION', $this->json()['error']['code']);

        $this->post('/api/v1/admin/deliveries/' . (string)$queued->getId() . '/requeue');
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    private function asAdmin(): void
    {
        $this->auth->setCaller(new Caller(DemoUserUuids::forNumber(1), 'admin@example.com', 'Admin', ['ROLE_USER', 'ROLE_ADMIN']));
    }

    private function seedDelivery(string $email, DeliveryStatus $status, string $type): Delivery
    {
        $notification = new Notification(Uuid::fromString(DemoUserUuids::forNumber(1)), $type, 'Subject', 'Inbox body', Severity::INFO, true);
        $delivery = new Delivery($notification, Channel::EMAIL, $email, 'Rendered subject', 'Rendered email body');
        if ($status === DeliveryStatus::FAILED) {
            $delivery->markSending();
            $delivery->markFailed('Permanent failure');
        } elseif ($status === DeliveryStatus::SENT) {
            $delivery->markSending();
            $delivery->markSent();
        }
        $this->em->persist($notification);
        $this->em->persist($delivery);
        $this->em->flush();

        return $delivery;
    }

    private function get(string $url): void
    {
        $this->client->request('GET', $url, server: ['HTTP_X_AUTH_TOKEN' => 'valid-token']);
    }

    private function post(string $url): void
    {
        $this->client->request('POST', $url, server: ['HTTP_X_AUTH_TOKEN' => 'valid-token']);
    }

    /** @return array<string, mixed> */
    private function json(): array
    {
        return json_decode((string)$this->client->getResponse()->getContent(), true);
    }
}
