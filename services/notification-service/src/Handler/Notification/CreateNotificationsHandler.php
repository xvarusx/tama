<?php

declare(strict_types=1);

namespace App\Handler\Notification;

use App\Entity\Delivery;
use App\Entity\Notification;
use App\Entity\NotificationPreference;
use App\Entity\ProcessedEvent;
use App\Enum\Channel;
use App\Message\NotificationRequested;
use App\Repository\NotificationPreferenceRepository;
use App\Repository\NotificationRepository;
use App\Repository\ProcessedEventRepository;
use App\Security\AuthServiceClient;
use App\Service\TemplateRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Consumes NotificationRequested idempotently. Audience resolution happens
 * before the transaction; everything else is committed atomically.
 */
class CreateNotificationsHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuthServiceClient $auth,
        private readonly TemplateRenderer $renderer,
        private readonly NotificationRepository $notifications,
        private readonly NotificationPreferenceRepository $preferences,
        private readonly ProcessedEventRepository $processedEvents,
    ) {}

    /** @return int Number of recipients for which a notification was created. */
    public function __invoke(NotificationRequested $event): int
    {
        // No HTTP call may hold a database transaction open.
        $recipients = $this->auth->resolveAudience($event->audience);

        // Fast path for transport retries. The primary key of processed_events
        // remains the concurrency guard if two consumers race past this check.
        if ($this->processedEvents->exists($event->eventId)) {
            return 0;
        }

        /** @var int $created */
        $created = $this->em->wrapInTransaction(function () use ($event, $recipients): int {
            // Reserve the event id first: a unique-key failure rolls back every
            // notification written for this delivery attempt.
            $this->em->persist(new ProcessedEvent($event->eventId, $event->source));
            $this->em->flush();

            $created = 0;
            foreach ($recipients as $recipient) {
                if (!Uuid::isValid($recipient->id)) {
                    continue;
                }

                $channels = $this->channelPreferences($recipient->id, $event->type);
                if (!$channels[Channel::IN_APP->value] && !$channels[Channel::EMAIL->value]) {
                    continue; // Both channels disabled: suppress entirely.
                }

                if ($event->dedupKey !== null && $this->notifications->hasDedupKey($recipient->id, $event->dedupKey)) {
                    continue; // Business duplicate for this recipient only.
                }

                $inApp = $this->renderer->render($event->type, Channel::IN_APP, $event->params);
                $notification = new Notification(
                    recipientId: Uuid::fromString($recipient->id),
                    type: $event->type,
                    title: $inApp['subject'],
                    body: $inApp['body'],
                    severity: $event->severity,
                    visibleInApp: $channels[Channel::IN_APP->value],
                    link: $event->link,
                    dedupKey: $event->dedupKey,
                );
                $this->em->persist($notification);

                if ($channels[Channel::EMAIL->value] && $recipient->email !== '') {
                    $email = $this->renderer->render($event->type, Channel::EMAIL, $event->params);
                    $this->em->persist(new Delivery(
                        notification: $notification,
                        channel: Channel::EMAIL,
                        recipientEmail: $recipient->email,
                        subject: $email['subject'],
                        body: $email['body'],
                    ));
                }

                ++$created;
            }

            $this->em->flush();

            return $created;
        });

        return $created;
    }

    /** @return array{in_app: bool, email: bool} */
    private function channelPreferences(string $userId, string $type): array
    {
        $enabled = [
            Channel::IN_APP->value => true,
            Channel::EMAIL->value => true,
        ];

        /** @var NotificationPreference[] $preferences */
        $preferences = $this->preferences->findBy(['userId' => $userId, 'type' => $type]);
        foreach ($preferences as $preference) {
            $enabled[$preference->getChannel()->value] = $preference->isEnabled();
        }

        return $enabled;
    }
}
