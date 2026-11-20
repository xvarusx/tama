<?php

declare(strict_types=1);

namespace App\Console;

use App\Exceptions\InvalidNotificationEvent;
use App\Handler\Notification\CreateNotificationsHandler;
use App\Message\NotificationRequested;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Builds a NotificationRequested locally and runs the consumer synchronously.
 * Exists so the service can be exercised before producers publish the contract.
 */
#[AsCommand(
    name: 'app:notifications:send',
    description: 'Create notifications for a role or a list of auth user ids.',
)]
final class SendNotificationCommand extends Command
{
    public function __construct(private readonly CreateNotificationsHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Event type, e.g. payment.installment.overdue')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'Audience role, e.g. ACCOUNTANT')
            ->addOption('user', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Audience user UUID (repeatable)')
            ->addOption('severity', null, InputOption::VALUE_REQUIRED, 'info, success, warning or critical')
            ->addOption('link', null, InputOption::VALUE_REQUIRED, 'Frontend deep link starting with /')
            ->addOption('param', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'key=value template parameter (repeatable)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $role = $input->getOption('role');
        $users = array_values(array_map(strval(...), (array)$input->getOption('user')));

        $params = [];
        foreach ((array)$input->getOption('param') as $param) {
            [$key, $value] = array_pad(explode('=', (string)$param, 2), 2, '');
            $params[$key] = $value;
        }

        $payload = [
            'eventId' => (string)Uuid::v7(),
            'occurredAt' => new DateTimeImmutable('now')->format(DATE_ATOM),
            'source' => 'cli',
            'type' => (string)$input->getOption('type'),
            'audience' => $role !== null && $role !== '' ? ['role' => (string)$role] : ['userIds' => $users],
            'params' => $params,
        ];

        if ($input->getOption('severity') !== null) {
            $payload['severity'] = (string)$input->getOption('severity');
        }
        if ($input->getOption('link') !== null) {
            $payload['link'] = (string)$input->getOption('link');
        }

        try {
            $event = NotificationRequested::fromArray($payload);
        } catch (InvalidNotificationEvent $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::INVALID;
        }

        $created = ($this->handler)($event);
        $output->writeln(sprintf('Created %d notification(s).', $created));

        return Command::SUCCESS;
    }
}
