<?php

declare(strict_types=1);

namespace App\Security;

use App\Exceptions\AuthServiceUnavailableException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP boundary to authentication-service. Read-only: no user table is
 * replicated into this service. Deliberately non-final so tests can replace it.
 */
class AuthServiceClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        #[Autowire(env: 'AUTH_SERVICE_URL')]
        private readonly string $authServiceUrl,
        #[Autowire(env: 'AUTH_INTERNAL_TOKEN')]
        private readonly string $internalToken,
    ) {}

    /** Resolve the X-AUTH-TOKEN through /me; null means an invalid or expired token. */
    public function authenticate(string $token): ?Caller
    {
        $cacheKey = 'auth.me.' . hash('sha256', $token);

        /** @var array{caller: array<string, mixed>|null} $payload */
        $payload = $this->cache->get($cacheKey, function (ItemInterface $item) use ($token): array {
            $item->expiresAfter(60);

            return ['caller' => $this->fetchCaller($token)?->toArray()];
        }, 0.0);

        $data = $payload['caller'] ?? null;
        if ($data === null) {
            return null;
        }

        return new Caller(
            (string)$data['id'],
            (string)$data['email'],
            (string)$data['fullName'],
            array_values(array_map(strval(...), (array)$data['roles'])),
        );
    }

    /**
     * Resolve an audience through the internal user directory.
     *
     * @param array{role?: string, userIds?: list<string>} $audience
     *
     * @return list<Recipient>
     */
    public function resolveAudience(array $audience): array
    {
        $role = $audience['role'] ?? null;
        if ($role !== null) {
            $normalized = str_starts_with($role, 'ROLE_') ? $role : 'ROLE_' . $role;
            $query = ['role' => $normalized];
        } else {
            $userIds = array_values(array_filter(
                (array)($audience['userIds'] ?? []),
                static fn(mixed $id): bool => Uuid::isValid((string)$id),
            ));
            $query = ['ids' => implode(',', $userIds)];
        }

        $url = rtrim($this->authServiceUrl, '/') . '/api/authenticator/internal/users?' . http_build_query($query);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => ['X-INTERNAL-TOKEN: ' . $this->internalToken],
            ]);
            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new AuthServiceUnavailableException();
            }
            /** @var array{data?: list<array<string, mixed>>} $payload */
            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface $exception) {
            throw new AuthServiceUnavailableException(previous: $exception);
        }

        $recipients = [];
        foreach ($payload['data'] ?? [] as $row) {
            $id = (string)($row['id'] ?? '');
            if (!Uuid::isValid($id)) {
                continue;
            }
            $recipients[] = new Recipient(
                $id,
                (string)($row['email'] ?? ''),
                (string)($row['fullName'] ?? ''),
                array_values(array_map(strval(...), (array)($row['roles'] ?? []))),
            );
        }

        return $recipients;
    }

    private function fetchCaller(string $token): ?Caller
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                rtrim($this->authServiceUrl, '/') . '/api/authenticator/me',
                ['headers' => ['X-AUTH-TOKEN: ' . $token]],
            );
            $statusCode = $response->getStatusCode();
            if ($statusCode === 401 || $statusCode === 403) {
                return null;
            }
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new AuthServiceUnavailableException();
            }
            /** @var array<string, mixed> $data */
            $data = $response->toArray(false);
        } catch (TransportExceptionInterface $exception) {
            throw new AuthServiceUnavailableException(previous: $exception);
        }

        $id = (string)($data['id'] ?? '');
        if (!Uuid::isValid($id) || (string)($data['email'] ?? '') === '') {
            throw new AuthServiceUnavailableException('The authentication service returned an invalid caller.');
        }

        return new Caller(
            $id,
            (string)$data['email'],
            (string)($data['fullName'] ?? ''),
            array_values(array_map(strval(...), (array)($data['roles'] ?? []))),
        );
    }
}
