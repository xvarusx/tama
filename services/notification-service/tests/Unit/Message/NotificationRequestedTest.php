<?php

declare(strict_types=1);

namespace App\Tests\Unit\Message;

use App\Enum\Severity;
use App\Exceptions\InvalidNotificationEvent;
use App\Message\NotificationRequested;
use App\Utils\DemoUserUuids;
use PHPUnit\Framework\TestCase;

final class NotificationRequestedTest extends TestCase
{
    public function testRejectsMissingAudience(): void
    {
        $this->expectException(InvalidNotificationEvent::class);
        $this->expectExceptionMessage('audience');

        NotificationRequested::fromArray($this->payload(['audience' => []]));
    }

    public function testRejectsBothRoleAndUserIds(): void
    {
        $this->expectException(InvalidNotificationEvent::class);

        NotificationRequested::fromArray($this->payload([
            'audience' => ['role' => 'ACCOUNTANT', 'userIds' => [DemoUserUuids::forNumber(1), DemoUserUuids::forNumber(2)]],
        ]));
    }

    public function testNormalizesSeverityAndDefaultsToInfo(): void
    {
        $default = NotificationRequested::fromArray($this->payload());
        self::assertSame(Severity::INFO, $default->severity);

        $explicit = NotificationRequested::fromArray($this->payload(['severity' => 'WARNING']));
        self::assertSame(Severity::WARNING, $explicit->severity);

        $critical = NotificationRequested::fromArray($this->payload(['severity' => 'CRITICAL']));
        self::assertSame(Severity::CRITICAL, $critical->severity);
    }

    public function testAcceptsUuidUserIdsAudience(): void
    {
        $first = DemoUserUuids::forNumber(1);
        $second = DemoUserUuids::forNumber(3);

        $event = NotificationRequested::fromArray($this->payload([
            'audience' => ['userIds' => [$first, $second]],
        ]));

        self::assertSame(['userIds' => [$first, $second]], $event->audience);
    }

    public function testAcceptsValidPayloadWithAllOptionalFields(): void
    {
        $event = NotificationRequested::fromArray($this->payload([
            'severity' => 'success',
            'link' => '/dashboard/invoices/9d2b7c1a',
            'dedupKey' => 'installment-3f2a:2026-11-15',
        ]));

        self::assertSame('6f1c2d1e-7a0b-4c55-9f6e-2b8d3a9c1e10', $event->eventId);
        self::assertSame('payment-service', $event->source);
        self::assertSame('payment.installment.overdue', $event->type);
        self::assertSame(['role' => 'ACCOUNTANT'], $event->audience);
        self::assertSame(['invoiceNumber' => 'F-012'], $event->params);
        self::assertSame('/dashboard/invoices/9d2b7c1a', $event->link);
        self::assertSame('installment-3f2a:2026-11-15', $event->dedupKey);
        self::assertSame(Severity::SUCCESS, $event->severity);
    }

    public function testRejectsMalformedEventIdAndLink(): void
    {
        try {
            NotificationRequested::fromArray($this->payload(['eventId' => 'not-a-uuid']));
            self::fail('Expected an invalid event id to be rejected.');
        } catch (InvalidNotificationEvent $exception) {
            self::assertStringContainsString('eventId', $exception->getMessage());
        }

        $this->expectException(InvalidNotificationEvent::class);
        NotificationRequested::fromArray($this->payload(['link' => 'https://example.com/invoices/1']));
    }

    public function testRejectsInvalidAudienceValuesAndParams(): void
    {
        try {
            NotificationRequested::fromArray($this->payload(['audience' => ['userIds' => [0, -2]]]));
            self::fail('Expected non-UUID user ids to be rejected.');
        } catch (InvalidNotificationEvent $exception) {
            self::assertStringContainsString('userIds', $exception->getMessage());
        }

        try {
            NotificationRequested::fromArray($this->payload(['audience' => ['userIds' => ['not-a-uuid']]]));
            self::fail('Expected malformed UUID user ids to be rejected.');
        } catch (InvalidNotificationEvent $exception) {
            self::assertStringContainsString('userIds', $exception->getMessage());
        }

        $this->expectException(InvalidNotificationEvent::class);
        NotificationRequested::fromArray($this->payload(['params' => ['nested' => ['a' => 1]]]));
    }

    public function testRejectsMissingRequiredKeys(): void
    {
        $this->expectException(InvalidNotificationEvent::class);

        NotificationRequested::fromArray(['eventId' => '6f1c2d1e-7a0b-4c55-9f6e-2b8d3a9c1e10']);
    }

    public function testRejectsMalformedDecodedMessage(): void
    {
        $this->expectException(InvalidNotificationEvent::class);
        $this->expectExceptionMessage('audience');

        // Symfony Messenger decodes broker JSON directly into the constructor.
        new NotificationRequested(
            '6f1c2d1e-7a0b-4c55-9f6e-2b8d3a9c1e10',
            '2026-11-15T09:00:00Z',
            'payment-service',
            'payment.installment.overdue',
            ['role' => 'ACCOUNTANT', 'userIds' => [DemoUserUuids::forNumber(1)]],
            [],
        );
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'eventId' => '6f1c2d1e-7a0b-4c55-9f6e-2b8d3a9c1e10',
            'occurredAt' => '2026-11-15T09:00:00Z',
            'source' => 'payment-service',
            'type' => 'payment.installment.overdue',
            'audience' => ['role' => 'ACCOUNTANT'],
            'params' => ['invoiceNumber' => 'F-012'],
        ], $overrides);
    }
}
