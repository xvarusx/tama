<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1;

use App\Entity\Notification;
use App\Enum\NotificationStatus;
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

final class NotificationsControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private FakeAuthServiceClient $auth;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // The test database is SQLite in memory: keep the same kernel (and the
        // same connection) across the seeded rows and the HTTP requests.
        $this->client->disableReboot();

        $container = $this->client->getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->em->getConfiguration()->setUseSchemaEditorApi(true);
        new SchemaTool($this->em)->createSchema($this->em->getMetadataFactory()->getAllMetadata());

        $this->auth = $container->get(FakeAuthServiceClient::class);
        $this->auth->setCaller(new Caller(DemoUserUuids::forNumber(1), 'jane@example.com', 'Jane Doe', ['ROLE_USER']));
    }

    public function testListsOnlyCallerVisibleNotifications(): void
    {
        $this->seed(DemoUserUuids::forNumber(1), 'Mine unread');
        $this->seed(DemoUserUuids::forNumber(1), 'Mine read', NotificationStatus::READ);
        $this->seed(DemoUserUuids::forNumber(1), 'Email only', NotificationStatus::UNREAD, false);
        $this->seed(DemoUserUuids::forNumber(2), 'Other user');

        $this->get('/api/v1/notifications');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $this->json();
        self::assertSame('success', $body['status']);
        self::assertCount(2, $body['data']);
        self::assertSame(1, $body['meta']['unreadCount']);
        self::assertArrayHasKey('pagination', $body);
        $titles = array_column(array_column($body['data'], 'attributes'), 'title');
        self::assertSame(['Mine read', 'Mine unread'], $titles);
        self::assertSame(DemoUserUuids::forNumber(1), $body['data'][0]['attributes']['recipientId']);
    }

    public function testUnreadCountExcludesHiddenAndArchived(): void
    {
        $this->seed(DemoUserUuids::forNumber(1), 'Unread');
        $this->seed(DemoUserUuids::forNumber(1), 'Archived', NotificationStatus::ARCHIVED);
        $this->seed(DemoUserUuids::forNumber(1), 'Hidden unread', NotificationStatus::UNREAD, false);

        $this->get('/api/v1/notifications/unread-count');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(1, $this->json()['data']['count']);
    }

    public function testShowReturns404ForAnotherRecipient(): void
    {
        $other = $this->seed(DemoUserUuids::forNumber(2), 'Not yours');

        $this->get('/api/v1/notifications/' . (string)$other->getId());

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('NOTIFICATION_NOT_FOUND', $this->json()['error']['code']);
    }

    public function testMarkReadIsIdempotent(): void
    {
        $notification = $this->seed(DemoUserUuids::forNumber(1), 'Read me');
        $url = '/api/v1/notifications/' . (string)$notification->getId() . '/read';

        $this->post($url);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame('read', $this->json()['data']['attributes']['status']);

        $this->post($url);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame('read', $this->json()['data']['attributes']['status']);
        self::assertNotNull($this->em->find(Notification::class, $notification->getId())->getReadAt());
    }

    public function testReadAllMarksOnlyVisibleUnread(): void
    {
        $visible = $this->seed(DemoUserUuids::forNumber(1), 'Visible unread');
        $this->seed(DemoUserUuids::forNumber(1), 'Already read', NotificationStatus::READ);
        $hidden = $this->seed(DemoUserUuids::forNumber(1), 'Hidden unread', NotificationStatus::UNREAD, false);

        $this->post('/api/v1/notifications/read-all');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(1, $this->json()['data']['updated']);
        $this->em->clear();
        // Bulk actions use DQL UPDATE: re-read the rows to see the new state.
        self::assertSame(NotificationStatus::READ, $this->em->find(Notification::class, $visible->getId())->getStatus());
        self::assertSame(NotificationStatus::UNREAD, $this->em->find(Notification::class, $hidden->getId())->getStatus());
    }

    public function testArchiveUnreadAndArchivedIsIdempotent(): void
    {
        $notification = $this->seed(DemoUserUuids::forNumber(1), 'Archive me');
        $url = '/api/v1/notifications/' . (string)$notification->getId() . '/archive';

        $this->post($url);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame('archived', $this->json()['data']['attributes']['status']);

        $this->post($url);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame('archived', $this->json()['data']['attributes']['status']);

        // An archived notification can never be read again.
        $this->post('/api/v1/notifications/' . (string)$notification->getId() . '/read');
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('INVALID_NOTIFICATION_TRANSITION', $this->json()['error']['code']);

        $read = $this->seed(DemoUserUuids::forNumber(1), 'Read to archive', NotificationStatus::READ);
        $this->post('/api/v1/notifications/archive-read');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(1, $this->json()['data']['updated']);
        self::assertSame(NotificationStatus::ARCHIVED, $this->em->find(Notification::class, $read->getId())->getStatus());
    }

    public function testMissingTokenReturns401(): void
    {
        $this->client->request('GET', '/api/v1/notifications');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame('UNAUTHENTICATED', $this->json()['error']['code']);
    }

    private function seed(
        string $recipientId,
        string $title,
        NotificationStatus $status = NotificationStatus::UNREAD,
        bool $visibleInApp = true,
    ): Notification {
        $notification = new Notification(
            Uuid::fromString($recipientId),
            'payment.installment.overdue',
            $title,
            'Body of ' . $title,
            Severity::WARNING,
            $visibleInApp,
        );
        if ($status !== NotificationStatus::UNREAD) {
            if ($status === NotificationStatus::READ) {
                $notification->markAsRead();
            } else {
                $notification->archive();
            }
        }
        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
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
