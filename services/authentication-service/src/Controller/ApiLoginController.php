<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Authentication', description: 'Login, current-user lookup and logout endpoints')]
final class ApiLoginController extends AbstractController
{
    #[Route('/api/authenticator/user_login', name: 'app_api_user_login', methods: ['POST'])]
    #[OA\Post(
        operationId: 'loginUser',
        tags: ['Authentication'],
        summary: 'Log in with email and password',
        description: 'Authenticates through the security firewall and returns the API token used as X-AUTH-TOKEN by other services.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: '••••••••'),
                ],
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Authenticated; returns the user and API token',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '0190f3d4-5b6c-7d8e-9f01-2a3b4c5d6e7f'),
                        new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                        new OA\Property(property: 'token', type: 'string', example: 'a1b2c3...'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: Response::HTTP_UNAUTHORIZED,
                description: 'Invalid credentials',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Invalid credentials.')],
                    type: 'object',
                ),
            ),
        ],
    )]
    public function login(): JsonResponse
    {
        return $this->json(['message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
    }

    #[Route('/api/authenticator/me', name: 'app_api_me', methods: ['GET'])]
    #[OA\Get(
        operationId: 'currentUser',
        tags: ['Authentication'],
        summary: 'Get the currently authenticated user',
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'The authenticated user',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '0190f3d4-5b6c-7d8e-9f01-2a3b4c5d6e7f'),
                        new OA\Property(property: 'fullName', type: 'string', example: 'Jane Doe'),
                        new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['ROLE_USER']),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: Response::HTTP_UNAUTHORIZED,
                description: 'Authentication required',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Authentication required.')],
                    type: 'object',
                ),
            ),
        ],
    )]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId()->toRfc4122(),
            'fullName' => $user->getFullName(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }

    #[Route('/api/authenticator/user_logout', name: 'app_api_user_logout', methods: ['POST'])]
    #[OA\Post(
        operationId: 'logoutUser',
        tags: ['Authentication'],
        summary: 'Log out the current user',
        description: 'Clears the stored API token and records the logout time.',
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'User logged out',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Logged out.')],
                    type: 'object',
                ),
            ),
        ],
    )]
    public function logout(EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if ($user instanceof User) {
            $user->setApiToken(null);
            $user->setLogoutAt();
            $entityManager->flush();
        }

        return $this->json(['message' => 'Logged out.']);
    }
}
