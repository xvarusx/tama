<?php

declare(strict_types=1);

namespace App\Console;

use App\Repository\NotificationRepository;
use App\Service\SettingProvider;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Deletes archived notifications past the configured retention period. */
#[AsCommand(
    name: 'app:notifications:purge',
    description: 'Purge archived notifications older than notifications.retention_days.',
)]
final class PurgeNotificationsCommand extends Command
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly SettingProvider $settings,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = $this->settings->int('notifications.retention_days');
        $before = new DateTimeImmutable()->modify(sprintf('-%d days', max(1, $days)));
        $purged = $this->notifications->purgeArchivedBefore($before);

        $output->writeln(sprintf('Purged %d notification(s).', $purged));

        return Command::SUCCESS;
    }
}
