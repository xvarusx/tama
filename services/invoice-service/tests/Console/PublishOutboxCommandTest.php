<?php

declare(strict_types=1);

namespace App\Tests\Console;

use App\Console\PublishOutboxCommand;
use App\Entity\OutboxEvent;
use App\Message\OutboxEventMessage;
use App\Repository\OutboxEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class PublishOutboxCommandTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();

        $em = $this->em();
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testPublishesAndMarksPendingRows(): void
    {
        $this->seedOutbox('invoice.created');
        $this->seedOutbox('invoice.issued');

        $tester = new CommandTester(self::getContainer()->get(PublishOutboxCommand::class));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Published 2 event(s).', $tester->getDisplay());
        self::assertSame(0, $this->outbox()->countPending());
    }

    public function testPublishedEventsCarryTheEventTypeAsAmqpTypeHeader(): void
    {
        $this->seedOutbox('invoice.issued');
        $captured = null;

        $bus = self::createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message, array $stamps) use (&$captured): Envelope {
                $captured = [$message, $stamps];

                return new Envelope($message, $stamps);
            });

        new CommandTester(new PublishOutboxCommand($this->outbox(), $bus, $this->em()))->execute([]);

        self::assertNotNull($captured);
        [$message, $stamps] = $captured;
        self::assertInstanceOf(OutboxEventMessage::class, $message);
        self::assertSame('invoice.issued', $message->eventType);

        $amqp = null;
        foreach ($stamps as $stamp) {
            if ($stamp instanceof AmqpStamp) {
                $amqp = $stamp;
            }
        }

        self::assertNotNull($amqp);
        self::assertSame('invoice.issued', $amqp->getAttributes()['type'] ?? null);
    }

    public function testDispatchFailureLeavesRowsUnpublished(): void
    {
        $this->seedOutbox('invoice.created');
        $bus = self::createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new RuntimeException('broker down'));
        $command = new PublishOutboxCommand($this->outbox(), $bus, $this->em());

        try {
            new CommandTester($command)->execute([]);
            self::fail('A dispatch failure must surface so the scheduler retries.');
        } catch (RuntimeException $exception) {
            self::assertSame('broker down', $exception->getMessage());
        }

        self::assertSame(1, $this->outbox()->countPending());
    }

    public function testNothingToPublishIsSuccessful(): void
    {
        $tester = new CommandTester(self::getContainer()->get(PublishOutboxCommand::class));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Published 0 event(s).', $tester->getDisplay());
    }

    private function seedOutbox(string $eventType): void
    {
        $em = $this->em();
        $em->persist(new OutboxEvent($eventType, 1, ['invoiceId' => 'x']));
        $em->flush();
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function outbox(): OutboxEventRepository
    {
        return self::getContainer()->get(OutboxEventRepository::class);
    }
}
