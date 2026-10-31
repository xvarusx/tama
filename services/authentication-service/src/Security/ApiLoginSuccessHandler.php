<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final class ApiLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('The authenticated principal must be a user entity.');
        }

        $apiToken = bin2hex(random_bytes(32));
        $user->setApiToken(hash('sha256', $apiToken));
        $this->entityManager->flush();

        return new JsonResponse([
            'token' => $apiToken,
            'user' => [
                'id' => $user->getId(),
                'fullName' => $user->getFullName(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }
}
