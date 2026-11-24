<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\Severity;
use App\Exceptions\InvalidNotificationEvent;
use DateTimeImmutable;
use Exception;
use Symfony\Component\Uid\Uuid;
use ValueError;

/**
 * Immutable integration event contract. Only additive changes are allowed.
 * Unknown or malformed payloads are rejected before any row is written.
 */
final readonly class NotificationRequested
{
    /**
     * @param array{role?: string, userIds?: list<string>}        $audience
     * @param array<string, string|int|float|bool>                $params
     */
    public function __construct(
        public string $eventId,
        public string $occurredAt,
        public string $source,
        public string $type,
        public array $audience,
        public array $params,
        public ?string $link = null,
        public Severity $severity = Severity::INFO,
        public ?string $dedupKey = null,
    ) {
        // Messenger decodes broker payloads straight into this constructor,
        // bypassing fromArray(). Re-check the invariant here so a malformed
        // message is rejected instead of being partially processed.
        if (!Uuid::isValid($eventId) || !self::isIso8601($occurredAt)) {
            throw new InvalidNotificationEvent('"eventId" and "occurredAt" must be a UUID and an ISO-8601 timestamp.');
        }
        if (trim($source) === '' || trim($type) === '') {
            throw new InvalidNotificationEvent('"source" and "type" must be non-empty strings.');
        }
        $hasRole = \array_key_exists('role', $audience);
        $hasUserIds = \array_key_exists('userIds', $audience);
        if ($hasRole === $hasUserIds) {
            throw new InvalidNotificationEvent('"audience" must contain exactly one of "role" or "userIds".');
        }
        if ($hasUserIds) {
            self::parseUserIds($audience['userIds']);
        }
        self::assertScalarParams($params);
        if ($link !== null && !str_starts_with($link, '/')) {
            throw new InvalidNotificationEvent('"link" must start with "/".');
        }
        if ($dedupKey !== null && trim($dedupKey) === '') {
            throw new InvalidNotificationEvent('"dedupKey" must be a non-empty string.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws InvalidNotificationEvent
     */
    public static function fromArray(array $payload): self
    {
        foreach (['eventId', 'occurredAt', 'source', 'type', 'audience', 'params'] as $required) {
            if (!\array_key_exists($required, $payload)) {
                throw new InvalidNotificationEvent(sprintf('Missing required key "%s".', $required));
            }
        }

        $eventId = $payload['eventId'];
        if (!\is_string($eventId) || !Uuid::isValid($eventId)) {
            throw new InvalidNotificationEvent('"eventId" must be a valid UUID.');
        }

        $occurredAt = $payload['occurredAt'];
        if (!\is_string($occurredAt) || !self::isIso8601($occurredAt)) {
            throw new InvalidNotificationEvent('"occurredAt" must be an ISO-8601 timestamp.');
        }

        $source = $payload['source'];
        if (!\is_string($source) || trim($source) === '') {
            throw new InvalidNotificationEvent('"source" must be a non-empty string.');
        }

        $type = $payload['type'];
        if (!\is_string($type) || trim($type) === '') {
            throw new InvalidNotificationEvent('"type" must be a non-empty string.');
        }

        $audience = self::parseAudience($payload['audience']);
        $params = self::parseParams($payload['params']);

        $severity = Severity::default();
        if (\array_key_exists('severity', $payload)) {
            $rawSeverity = $payload['severity'];
            try {
                $severity = Severity::fromString(\is_string($rawSeverity) ? $rawSeverity : null);
            } catch (ValueError) {
                throw new InvalidNotificationEvent(sprintf('Invalid severity "%s".', self::stringify($rawSeverity)));
            }
        }

        $link = null;
        if (\array_key_exists('link', $payload) && $payload['link'] !== null) {
            if (!\is_string($payload['link']) || !str_starts_with($payload['link'], '/')) {
                throw new InvalidNotificationEvent('"link" must start with "/".');
            }
            $link = $payload['link'];
        }

        $dedupKey = null;
        if (\array_key_exists('dedupKey', $payload) && $payload['dedupKey'] !== null) {
            if (!\is_string($payload['dedupKey']) || trim($payload['dedupKey']) === '') {
                throw new InvalidNotificationEvent('"dedupKey" must be a non-empty string.');
            }
            $dedupKey = $payload['dedupKey'];
        }

        return new self(
            $eventId,
            $occurredAt,
            trim($source),
            trim($type),
            $audience,
            $params,
            $link,
            $severity,
            $dedupKey,
        );
    }

    /**
     * @return array{role?: string, userIds?: list<string>}
     */
    private static function parseAudience(mixed $value): array
    {
        if (!\is_array($value)) {
            throw new InvalidNotificationEvent('"audience" must contain exactly one of "role" or "userIds".');
        }

        $hasRole = \array_key_exists('role', $value);
        $hasUserIds = \array_key_exists('userIds', $value);
        if ($hasRole === $hasUserIds) {
            throw new InvalidNotificationEvent('"audience" must contain exactly one of "role" or "userIds".');
        }

        if ($hasRole) {
            if (!\is_string($value['role']) || trim($value['role']) === '') {
                throw new InvalidNotificationEvent('"audience.role" must be a non-empty string.');
            }

            return ['role' => trim($value['role'])];
        }

        return ['userIds' => self::parseUserIds($value['userIds'])];
    }

    /**
     * @return list<string>
     *
     * @throws InvalidNotificationEvent
     */
    private static function parseUserIds(mixed $userIds): array
    {
        if (!\is_array($userIds) || $userIds === []) {
            throw new InvalidNotificationEvent('"audience.userIds" must be a non-empty array of UUID strings.');
        }

        $ids = [];
        foreach ($userIds as $id) {
            if (!\is_string($id) || !Uuid::isValid($id)) {
                throw new InvalidNotificationEvent('"audience.userIds" must be a non-empty array of UUID strings.');
            }
            $ids[] = $id;
        }

        return $ids;
    }

    /** @return array<string, string|int|float|bool> */
    private static function parseParams(mixed $value): array
    {
        if (!\is_array($value)) {
            throw new InvalidNotificationEvent('"params" must be a flat map of scalar values.');
        }

        $params = [];
        foreach ($value as $key => $item) {
            if (!\is_string($key) || !(\is_string($item) || \is_int($item) || \is_float($item) || \is_bool($item))) {
                throw new InvalidNotificationEvent('"params" must be a flat map of scalar values.');
            }
            $params[$key] = $item;
        }

        return $params;
    }

    /**
     * Accepts array<mixed> so decoded payloads can be checked at runtime
     * without weakening the public constructor's PHPDoc type.
     *
     * @param array<mixed> $params
     */
    private static function assertScalarParams(array $params): void
    {
        foreach ($params as $key => $value) {
            if (!\is_string($key) || !(\is_string($value) || \is_int($value) || \is_float($value) || \is_bool($value))) {
                throw new InvalidNotificationEvent('"params" must be a flat map of scalar values.');
            }
        }
    }

    private static function isIso8601(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
            return false;
        }

        try {
            new DateTimeImmutable($value);
        } catch (Exception) {
            return false;
        }

        return true;
    }

    private static function stringify(mixed $value): string
    {
        return \is_scalar($value) ? (string)$value : \get_debug_type($value);
    }
}
