<?php

declare(strict_types=1);

namespace App\Console;

use App\Handler\Delivery\ProcessDeliveriesHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Worker entry point called by the compose `notification-service-worker` loop. */
#[AsCommand(
    name: 'app:deliveries:process',
    description: 'Send due email deliveries from the outbox with retries and stale-claim recovery.',
)]
final class ProcessDeliveriesCommand extends Command
{
    public function __construct(private readonly ProcessDeliveriesHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'limit',
            null,
            InputOption::VALUE_REQUIRED,
            'Maximum number of deliveries to claim in this run.',
            '50',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = max(1, (int)$input->getOption('limit'));
        $result = ($this->handler)($limit);

        $output->writeln(sprintf(
            'Processed deliveries: %d sent, %d retried, %d failed.',
            $result['sent'],
            $result['retried'],
            $result['failed'],
        ));

        return Command::SUCCESS;
    }
}
