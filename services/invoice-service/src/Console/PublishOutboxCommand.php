<?php

declare(strict_types=1);

namespace App\Console;

use App\Message\OutboxEventMessage;
use App\Repository\OutboxEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Relays pending outbox rows to the broker, then marks them published in one
 * flush. A dispatch failure aborts before the flush, so every row stays
 * pending and the next run retries it.
 */
#[AsCommand(name: 'app:outbox:publish', description: 'Publish pending outbox events to the message broker.')]
final class PublishOutboxCommand extends Command
{
    private const int BATCH_SIZE = 100;

    public function __construct(
        private readonly OutboxEventRepository $outbox,
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $events = $this->outbox->findPending(self::BATCH_SIZE);

        foreach ($events as $event) {
            $this->bus->dispatch(
                OutboxEventMessage::fromOutbox($event),
                [new AmqpStamp(attributes: ['type' => $event->getEventType()])],
            );
            $event->markPublished();
        }

        $this->em->flush();

        $output->writeln(sprintf('Published %d event(s).', \count($events)));

        return Command::SUCCESS;
    }
}
