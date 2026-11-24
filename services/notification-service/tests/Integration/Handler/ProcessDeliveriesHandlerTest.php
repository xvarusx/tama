<?php

declare(strict_types=1);

namespace App\Tests\Integration\Handler;

use App\Entity\Delivery;
use App\Entity\Notification;
use App\Entity\Setting;
use App\Enum\Channel;
use App\Enum\DeliveryStatus;
use App\Enum\Severity;
use App\Handler\Delivery\ProcessDeliveriesHandler;
use App\Mail\DeliverySendException;
use App\Repository\DeliveryAttemptRepository;
use App\Repository\DeliveryRepository;
use App\Service\SettingProvider;
use App\Tests\Support\FakeDeliverySender;
use App\Utils\DemoUserUuids;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class ProcessDeliveriesHandlerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ProcessDeliveriesHandler $handler;
    private FakeDeliverySender $sender;
    private DeliveryRepository $deliveries;
    private DeliveryAttemptRepository $attempts;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->em->getConfiguration()->setUseSchemaEditorApi(true);
        new SchemaTool($this->em)->createSchema($this->em->getMetadataFactory()->getAllMetadata());

        $this->handler = $container->get(ProcessDeliveriesHandler::class);
        $this->sender = $container->get(FakeDeliverySender::class);
        $this->deliveries = $container->get(DeliveryRepository::class);
        $this->attempts = $container->get(DeliveryAttemptRepository::class);

        $this->sender->setFailure(null);
        $this->em->persist(new Setting('notifications.retry_base_seconds', '30', 'Base backoff'));
        $this->em->persist(new Setting('notifications.retry_max_seconds', '300', 'Max backoff'));
        $this->em->persist(new Setting('notifications.max_attempts', '3', 'Maximum attempts'));
        $this->em->persist(new Setting('notifications.sending_timeout_seconds', '600', 'Stale timeout'));
        $this->em->flush();
        $container->get(SettingProvider::class)->invalidate();
    }

    public function testSendsDueDeliveryAndMarksSentWithAttempt(): void
    {
        $delivery = $this->seedDelivery();

        $result = ($this->handler)(50);

        self::assertSame(['sent' => 1, 'retried' => 0, 'failed' => 0], $result);
        $this->em->clear();
        $stored = $this->deliveries->find($delivery->getId());
        self::assertNotNull($stored);
        self::assertSame(DeliveryStatus::SENT, $stored->getStatus());
        self::assertSame(1, $stored->getAttempts());
        self::assertNotNull($stored->getSentAt());
        self::assertCount(1, $this->attempts->findBy(['delivery' => $stored]));
        $attempt = $this->attempts->findOneBy(['delivery' => $stored]);
        self::assertSame(1, $attempt->getAttemptNumber());
        self::assertSame('provider-msg-1', $attempt->getProviderMessageId());
        self::assertNull($attempt->getError());
        self::assertSame([
            'email' => 'jane@example.com',
            'subject' => 'Overdue invoice',
            'body' => '<p>Overdue</p>',
            'fromAddress' => 'no-reply@example.com',
            'fromName' => 'ERP Notifications',
        ], $this->sender->sent[0]);
    }

    public function testTemporaryFailureSchedulesRetryAndIncrementsAttempts(): void
    {
        $delivery = $this->seedDelivery();
        $this->sender->setFailure(new DeliverySendException('SMTP 451 temporary', false));
        $before = new DateTimeImmutable();

        $result = ($this->handler)(50);

        self::assertSame(['sent' => 0, 'retried' => 1, 'failed' => 0], $result);
        $this->em->clear();
        $stored = $this->deliveries->find($delivery->getId());
        self::assertSame(DeliveryStatus::RETRY_WAITING, $stored->getStatus());
        self::assertSame(1, $stored->getAttempts());
        self::assertSame('SMTP 451 temporary', $stored->getLastError());
        self::assertGreaterThan($before, $stored->getNextAttemptAt());
        $attempt = $this->attempts->findOneBy(['delivery' => $stored]);
        self::assertSame('SMTP 451 temporary', $attempt->getError());
        self::assertNull($attempt->getProviderMessageId());
    }

    public function testPermanentFailureMarksFailedImmediately(): void
    {
        $delivery = $this->seedDelivery();
        $this->sender->setFailure(new DeliverySendException('Invalid recipient address', true));

        $result = ($this->handler)(50);

        self::assertSame(['sent' => 0, 'retried' => 0, 'failed' => 1], $result);
        $this->em->clear();
        $stored = $this->deliveries->find($delivery->getId());
        self::assertSame(DeliveryStatus::FAILED, $stored->getStatus());
        self::assertSame('Invalid recipient address', $stored->getLastError());
        self::assertNull($stored->getNextAttemptAt());
        self::assertCount(1, $this->attempts->findBy(['delivery' => $stored]));
    }

    public function testMaxAttemptsMarksFailed(): void
    {
        $delivery = $this->seedDelivery();
        $delivery->markSending();     // attempt 1
        $delivery->reclaimStale();    // attempt 2, retry waiting
        $this->em->flush();
        $this->sender->setFailure(new DeliverySendException('Still down', false));

        $result = ($this->handler)(50);

        self::assertSame(['sent' => 0, 'retried' => 0, 'failed' => 1], $result);
        $this->em->clear();
        $stored = $this->deliveries->find($delivery->getId());
        self::assertSame(DeliveryStatus::FAILED, $stored->getStatus());
        self::assertSame(3, $stored->getAttempts());
        self::assertSame('Still down', $stored->getLastError());
    }

    public function testStaleSendingRowIsReclaimedAndRetried(): void
    {
        $stale = $this->seedDelivery();
        $stale->markSending();
        $this->em->flush();
        $this->em->getConnection()->executeStatement(
            'UPDATE deliveries SET sending_at = :old WHERE id = :id',
            ['old' => new DateTimeImmutable('-700 seconds')->format('Y-m-d H:i:s'), 'id' => $stale->getId()->toBinary()],
        );

        // A freshly claimed row must not be reclaimed or sent.
        $fresh = $this->seedDelivery('john@example.com');
        $fresh->markSending();
        $this->em->flush();

        $result = ($this->handler)(50);

        self::assertSame(['sent' => 1, 'retried' => 0, 'failed' => 0], $result);
        $this->em->clear();
        self::assertSame(DeliveryStatus::SENT, $this->deliveries->find($stale->getId())->getStatus());
        self::assertSame(3, $this->deliveries->find($stale->getId())->getAttempts());
        self::assertSame(DeliveryStatus::SENDING, $this->deliveries->find($fresh->getId())->getStatus());
        self::assertSame(1, $this->deliveries->find($fresh->getId())->getAttempts());
        self::assertCount(1, $this->sender->sent);
    }

    public function testFutureRetryIsNotClaimed(): void
    {
        $delivery = $this->seedDelivery();
        $delivery->markSending();
        $delivery->scheduleRetry(3600, 'Back off');
        $this->em->flush();

        $result = ($this->handler)(50);

        self::assertSame(['sent' => 0, 'retried' => 0, 'failed' => 0], $result);
        self::assertCount(0, $this->sender->sent);
        $this->em->clear();
        $stored = $this->deliveries->find($delivery->getId());
        self::assertSame(DeliveryStatus::RETRY_WAITING, $stored->getStatus());
        self::assertSame(1, $stored->getAttempts());
    }

    private function seedDelivery(string $email = 'jane@example.com'): Delivery
    {
        $notification = new Notification(Uuid::fromString(DemoUserUuids::forNumber(1)), 'payment.installment.overdue', 'Overdue invoice', 'Overdue', Severity::WARNING, true);
        $delivery = new Delivery($notification, Channel::EMAIL, $email, 'Overdue invoice', '<p>Overdue</p>');
        $this->em->persist($notification);
        $this->em->persist($delivery);
        $this->em->flush();

        return $delivery;
    }
}
