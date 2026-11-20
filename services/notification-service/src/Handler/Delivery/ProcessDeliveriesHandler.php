<?php

declare(strict_types=1);

namespace App\Handler\Delivery;

use App\Entity\Delivery;
use App\Entity\DeliveryAttempt;
use App\Enum\DeliveryStatus;
use App\Mail\DeliverySender;
use App\Mail\DeliverySendException;
use App\Repository\DeliveryRepository;
use App\Service\SettingProvider;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * Database-outbox worker: reclaims crashed claims, atomically claims due rows,
 * sends each one through the mail boundary and records the outcome. Every
 * delivery is handled independently so one bad row cannot stop the loop.
 */
final readonly class ProcessDeliveriesHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private DeliveryRepository $deliveries,
        private SettingProvider $settings,
        private DeliverySender $sender,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return array{sent: int, retried: int, failed: int}
     */
    public function __invoke(int $limit = 50): array
    {
        $counts = ['sent' => 0, 'retried' => 0, 'failed' => 0];
        $this->reclaimStale();

        // Captured after reclaiming: a reclaimed row has nextAttemptAt = now.
        $now = new DateTimeImmutable();
        $candidates = $this->deliveries->findDue(max(1, $limit), $now);

        foreach ($candidates as $candidate) {
            $id = $candidate->getId();
            if ($this->claim($id, $now) === 0) {
                continue; // Another worker claimed it first.
            }

            // The DQL UPDATE bypassed the identity map; reload the claimed row.
            $this->em->clear();
            $delivery = $this->em->find(Delivery::class, $id);
            if ($delivery === null) {
                continue;
            }

            try {
                $messageId = $this->sender->send(
                    $delivery,
                    $this->settings->string('notifications.from_address'),
                    $this->settings->string('notifications.from_name'),
                );
                $this->recordSuccess($delivery, $messageId);
                ++$counts['sent'];
            } catch (DeliverySendException $exception) {
                $this->recordFailure($delivery, $exception->getMessage(), $exception->isPermanent());
                ++$counts[$exception->isPermanent() ? 'failed' : ($delivery->getStatus() === DeliveryStatus::RETRY_WAITING ? 'retried' : 'failed')];
            } catch (Throwable $exception) {
                $this->logger->error('Unexpected error while sending a delivery.', [
                    'deliveryId' => (string)$id,
                    'error' => $exception->getMessage(),
                ]);
                $this->recordFailure($delivery, $exception->getMessage(), true);
                ++$counts['failed'];
            }
        }

        return $counts;
    }

    private function reclaimStale(): void
    {
        $timeout = $this->settings->int('notifications.sending_timeout_seconds');
        $stale = $this->deliveries->findStaleSending(
            new DateTimeImmutable()->modify(sprintf('-%d seconds', max(1, $timeout))),
        );
        foreach ($stale as $delivery) {
            $delivery->reclaimStale();
        }
        if ($stale !== []) {
            $this->em->flush();
        }
    }

    /** Atomic, portable conditional claim: only a still-due row can change. */
    private function claim(Uuid $id, DateTimeImmutable $now): int
    {
        return (int)$this->em->createQueryBuilder()
            ->update(Delivery::class, 'd')
            ->set('d.status', ':sending')
            ->set('d.sendingAt', ':now')
            ->set('d.attempts', 'd.attempts + 1')
            ->where('d.id = :id')
            ->andWhere('d.status IN (:claimable)')
            ->andWhere('d.nextAttemptAt <= :now')
            ->setParameter('sending', DeliveryStatus::SENDING->value)
            ->setParameter('claimable', [DeliveryStatus::QUEUED->value, DeliveryStatus::RETRY_WAITING->value])
            ->setParameter('now', $now, 'datetime_immutable')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->execute();
    }

    private function recordSuccess(Delivery $delivery, string $messageId): void
    {
        $this->em->wrapInTransaction(function () use ($delivery, $messageId): void {
            $delivery->markSent();
            $this->em->persist(new DeliveryAttempt($delivery, $delivery->getAttempts(), $messageId, null));
            $this->em->flush();
        });
    }

    private function recordFailure(Delivery $delivery, string $error, bool $permanent): void
    {
        $attempts = $delivery->getAttempts();
        $maxAttempts = $this->settings->int('notifications.max_attempts');
        $retry = !$permanent && $attempts < $maxAttempts;

        $this->em->wrapInTransaction(function () use ($delivery, $error, $retry, $attempts): void {
            if ($retry) {
                $base = $this->settings->int('notifications.retry_base_seconds');
                $max = $this->settings->int('notifications.retry_max_seconds');
                $backoff = min($base * (2 ** max(0, $attempts - 1)), $max);
                $delivery->scheduleRetry($backoff, $error);
            } else {
                $delivery->markFailed($error);
            }
            $this->em->persist(new DeliveryAttempt($delivery, $attempts, null, $error));
            $this->em->flush();
        });
    }
}
