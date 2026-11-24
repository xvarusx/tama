<?php

declare(strict_types=1);

namespace App\Tests\Integration\Handler;

use App\Dto\NotificationFilter;
use App\Entity\Notification;
use App\Entity\NotificationPreference;
use App\Entity\NotificationTemplate;
use App\Enum\Channel;
use App\Enum\Severity;
use App\Exceptions\AuthServiceUnavailableException;
use App\Handler\Notification\CreateNotificationsHandler;
use App\Message\NotificationRequested;
use App\Repository\DeliveryRepository;
use App\Repository\NotificationRepository;
use App\Repository\ProcessedEventRepository;
use App\Security\Recipient;
use App\Tests\Support\FakeAuthServiceClient;
use App\Utils\DemoUserUuids;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class CreateNotificationsHandlerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CreateNotificationsHandler $handler;
    private FakeAuthServiceClient $auth;
    private NotificationRepository $notifications;
    private DeliveryRepository $deliveries;
    private ProcessedEventRepository $processedEvents;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->em->getConfiguration()->setUseSchemaEditorApi(true);
        new SchemaTool($this->em)->createSchema($this->em->getMetadataFactory()->getAllMetadata());

        $this->handler = $container->get(CreateNotificationsHandler::class);
        $this->auth = $container->get(FakeAuthServiceClient::class);
        $this->notifications = $container->get(NotificationRepository::class);
        $this->deliveries = $container->get(DeliveryRepository::class);
        $this->processedEvents = $container->get(ProcessedEventRepository::class);

        $this->auth->setCaller(null);
        $this->auth->setAudience([]);
        $this->auth->setAudienceFailure(null);
    }

    public function testCreatesNotificationAndQueuedDeliveryInOneTransaction(): void
    {
        $this->seedTemplate('payment.installment.overdue', Channel::EMAIL, 'Invoice {{ invoiceNumber }} overdue', '<p>{{ partyName }}</p>');
        $this->seedTemplate('payment.installment.overdue', Channel::IN_APP, 'Invoice {{ invoiceNumber }} overdue', 'Invoice {{ invoiceNumber }}: {{ partyName }}');
        $this->auth->setAudience([$this->recipient()]);

        $created = ($this->handler)($this->event());

        self::assertSame(1, $created);
        self::assertSame(1, $this->notifications->count([]));
        self::assertSame(1, $this->deliveries->count([]));
        self::assertSame(1, $this->processedEvents->count([]));

        $notification = $this->notifications->findAll()[0];
        self::assertSame('Invoice F-012 overdue', $notification->getTitle());
        self::assertSame('Invoice F-012: Jane Doe', $notification->getBody());
        self::assertTrue($notification->isVisibleInApp());
        self::assertSame(DemoUserUuids::forNumber(1), $notification->getRecipientId()->toRfc4122());

        $delivery = $this->deliveries->findAll()[0];
        self::assertSame('jane@example.com', $delivery->getRecipientEmail());
        self::assertSame('Invoice F-012 overdue', $delivery->getSubject());
        self::assertSame('<!DOCTYPE html><html><body><p>Jane Doe</p></body></html>', $delivery->getBody());
    }

    public function testDuplicateEventIdCreatesNothing(): void
    {
        $this->auth->setAudience([$this->recipient()]);
        $eventId = '6f1c2d1e-7a0b-4c55-9f6e-2b8d3a9c1e10';

        self::assertSame(1, ($this->handler)($this->event(['eventId' => $eventId])));
        self::assertSame(0, ($this->handler)($this->event([
            'eventId' => $eventId,
            'type' => 'invoice.issued',
            'params' => ['invoiceNumber' => 'F-999'],
        ])));

        self::assertSame(1, $this->notifications->count([]));
        self::assertSame(1, $this->processedEvents->count([]));
        self::assertSame('payment.installment.overdue', $this->notifications->findAll()[0]->getType());
    }

    public function testDuplicateDedupKeySkipsOnlyThatRecipient(): void
    {
        $jane = DemoUserUuids::forNumber(1);
        $john = DemoUserUuids::forNumber(2);
        $dedupKey = 'installment-3f2a:2026-11-15';
        $this->em->persist(new Notification(
            Uuid::fromString($jane),
            'payment.installment.overdue',
            'Already notified',
            'Existing notification.',
            Severity::INFO,
            true,
            null,
            $dedupKey,
        ));
        $this->em->flush();

        $this->auth->setAudience([$this->recipient(1), $this->recipient(2, 'john@example.com')]);

        self::assertSame(1, ($this->handler)($this->event(['dedupKey' => $dedupKey])));

        self::assertSame(2, $this->notifications->count([]));
        self::assertCount(1, $this->notifications->findBy(['recipientId' => $jane]));
        self::assertSame('Already notified', $this->notifications->findBy(['recipientId' => $jane])[0]->getTitle());
        self::assertCount(1, $this->notifications->findBy(['recipientId' => $john]));
        self::assertSame(1, $this->processedEvents->count([]));
    }

    public function testRoleAudienceCreatesOneNotificationPerRecipient(): void
    {
        $this->auth->setAudience([
            $this->recipient(1, 'jane@example.com'),
            $this->recipient(2, 'john@example.com'),
        ]);

        self::assertSame(2, ($this->handler)($this->event(['audience' => ['role' => 'ACCOUNTANT']])));

        self::assertSame(2, $this->notifications->count([]));
        self::assertSame(2, $this->deliveries->count([]));
        self::assertSame([['role' => 'ACCOUNTANT']], $this->auth->audienceCalls);
    }

    public function testExplicitUserIdsAudienceCreatesOneNotificationPerRecipient(): void
    {
        $jane = DemoUserUuids::forNumber(1);
        $alice = DemoUserUuids::forNumber(3);
        $this->auth->setAudience([
            $this->recipient(1, 'jane@example.com'),
            $this->recipient(3, 'alice@example.com'),
        ]);

        self::assertSame(2, ($this->handler)($this->event(['audience' => ['userIds' => [$jane, $alice]]])));

        self::assertSame(2, $this->notifications->count([]));
        self::assertSame([['userIds' => [$jane, $alice]]], $this->auth->audienceCalls);
    }

    public function testInAppDisabledEmailEnabledCreatesHiddenNotificationAndDelivery(): void
    {
        $jane = DemoUserUuids::forNumber(1);
        $this->seedPreference($jane, 'payment.installment.overdue', Channel::IN_APP, false);
        $this->auth->setAudience([$this->recipient()]);

        self::assertSame(1, ($this->handler)($this->event()));

        $notification = $this->notifications->findAll()[0];
        self::assertFalse($notification->isVisibleInApp());
        self::assertSame(1, $this->deliveries->count([]));
        self::assertSame(0, $this->notifications->countUnread($jane));

        [$inbox, $total] = $this->notifications->searchForRecipient($jane, new NotificationFilter());
        self::assertSame([], $inbox);
        self::assertSame(0, $total);
    }

    public function testBothChannelsDisabledCreatesNothing(): void
    {
        $jane = DemoUserUuids::forNumber(1);
        $this->seedPreference($jane, 'payment.installment.overdue', Channel::IN_APP, false);
        $this->seedPreference($jane, 'payment.installment.overdue', Channel::EMAIL, false);
        $this->auth->setAudience([$this->recipient()]);

        self::assertSame(0, ($this->handler)($this->event()));

        self::assertSame(0, $this->notifications->count([]));
        self::assertSame(0, $this->deliveries->count([]));
        self::assertSame(1, $this->processedEvents->count([]));
    }

    public function testRecipientWithMalformedUuidIsSkipped(): void
    {
        $this->auth->setAudience([
            new Recipient('not-a-uuid', 'broken@example.com', 'Broken', ['ROLE_USER']),
            $this->recipient(),
        ]);

        self::assertSame(1, ($this->handler)($this->event()));
        self::assertSame(1, $this->notifications->count([]));
    }

    public function testAuthServiceFailureLeavesNoRows(): void
    {
        $this->auth->setAudienceFailure(new AuthServiceUnavailableException());

        try {
            ($this->handler)($this->event());
            self::fail('Expected the auth failure to propagate to Messenger.');
        } catch (AuthServiceUnavailableException) {
        }

        self::assertSame(0, $this->notifications->count([]));
        self::assertSame(0, $this->deliveries->count([]));
        self::assertSame(0, $this->processedEvents->count([]));
    }

    public function testMissingTemplateStillCreatesNotificationAndDelivery(): void
    {
        $this->auth->setAudience([$this->recipient()]);

        self::assertSame(1, ($this->handler)($this->event()));

        $notification = $this->notifications->findAll()[0];
        self::assertSame('payment.installment.overdue', $notification->getTitle());
        self::assertSame('<p></p>', $notification->getBody());

        $delivery = $this->deliveries->findAll()[0];
        self::assertSame('payment.installment.overdue', $delivery->getSubject());
        self::assertSame('<!DOCTYPE html><html><body><p></p></body></html>', $delivery->getBody());
    }

    private function seedTemplate(string $type, Channel $channel, string $subject, string $body): void
    {
        $this->em->persist(new NotificationTemplate($type, $channel, 'en', $subject, $body));
        $this->em->flush();
    }

    private function seedPreference(string $userId, string $type, Channel $channel, bool $enabled): void
    {
        $this->em->persist(new NotificationPreference(Uuid::fromString($userId), $type, $channel, $enabled));
        $this->em->flush();
    }

    private function recipient(int $id = 1, string $email = 'jane@example.com'): Recipient
    {
        return new Recipient(DemoUserUuids::forNumber($id), $email, 'Jane Doe', ['ROLE_USER']);
    }

    /** @param array<string, mixed> $overrides */
    private function event(array $overrides = []): NotificationRequested
    {
        return NotificationRequested::fromArray(array_replace([
            'eventId' => '6f1c2d1e-7a0b-4c55-9f6e-2b8d3a9c1e10',
            'occurredAt' => '2026-11-15T09:00:00Z',
            'source' => 'payment-service',
            'type' => 'payment.installment.overdue',
            'audience' => ['role' => 'ACCOUNTANT'],
            'params' => [
                'invoiceNumber' => 'F-012',
                'partyName' => 'Jane Doe',
                'amount' => 20000,
                'currency' => 'TND',
            ],
        ], $overrides));
    }
}
