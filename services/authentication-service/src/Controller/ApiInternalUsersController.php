<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Read-only user directory for other services on the Docker network.
 * The gateway blocks /api/authenticator/internal/ from the outside; this
 * endpoint authorizes callers with a shared secret instead of a user session.
 */
#[OA\Tag(name: 'Internal users (private)', description: 'Docker-network-only user directory; not exposed through the public gateway')]
final class ApiInternalUsersController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        #[Autowire('%env(AUTH_INTERNAL_TOKEN)%')]
        private readonly string $internalToken,
    ) {
    }

    #[Route('/api/authenticator/internal/users', name: 'app_api_internal_users', methods: ['GET'])]
    #[OA\Get(
        operationId: 'internalUsers',
        tags: ['Internal users (private)'],
        summary: 'Look up verified users (internal services only)',
        description: 'Private endpoint: reachable only on the Docker network. The gateway blocks /api/authenticator/internal/. Requires the shared X-INTERNAL-TOKEN header and exactly one of "role" or "ids".',
        parameters: [
            new OA\Parameter(name: 'X-INTERNAL-TOKEN', description: 'Shared internal secret', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'role', description: 'Filter by role (mutually exclusive with ids)', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'ROLE_USER')),
            new OA\Parameter(name: 'ids', description: 'Comma-separated user UUIDs (mutually exclusive with role)', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: '0190f3d4-5b6c-7d8e-9f01-2a3b4c5d6e7f,0190f3d4-5b6c-7d8e-9f01-2a3b4c5d6e80')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Matching verified users',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '0190f3d4-5b6c-7d8e-9f01-2a3b4c5d6e7f'),
                                    new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                                    new OA\Property(property: 'fullName', type: 'string', example: 'Jane Doe'),
                                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['ROLE_USER']),
                                ],
                                type: 'object',
                            ),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: Response::HTTP_UNAUTHORIZED,
                description: 'Invalid internal token',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Invalid internal token.')],
                    type: 'object',
                ),
            ),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Provide exactly one of role or ids'),
        ],
    )]
    public function users(Request $request): JsonResponse
    {
        $providedToken = (string) $request->headers->get('X-INTERNAL-TOKEN', '');
        if (!hash_equals($this->internalToken, $providedToken)) {
            return $this->json(['message' => 'Invalid internal token.'], Response::HTTP_UNAUTHORIZED);
        }

        $role = $request->query->get('role');
        $ids = $request->query->get('ids');
        if ((null === $role) === (null === $ids)) {
            return $this->json(
                ['message' => 'Provide exactly one of "role" or "ids".'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (null !== $role) {
            $users = $this->users->findVerifiedByRole(trim((string) $role));
        } else {
            $parsedIds = array_values(array_filter(
                array_map('trim', explode(',', (string) $ids)),
                static fn (string $value): bool => '' !== $value,
            ));
            $invalidIds = array_filter($parsedIds, static fn (string $id): bool => !Uuid::isValid($id));
            if ([] === $parsedIds || [] !== $invalidIds) {
                return $this->json(
                    ['message' => '"ids" must be a non-empty list of valid UUIDs.'],
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }
            $users = $this->users->findVerifiedByIds($parsedIds);
        }

        return $this->json([
            'data' => array_map(static fn (User $user): array => [
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
                'fullName' => $user->getFullName(),
                'roles' => $user->getRoles(),
            ], $users),
        ]);
    }
}
