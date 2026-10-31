<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiLoginController extends AbstractController
{
    #[Route('/api/user_login', name: 'app_api_user_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        return $this->json(['message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
    }

    #[Route('/api/me', name: 'app_api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId(),
            'fullName' => $user->getFullName(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }

    #[Route('/api/user_logout', name: 'app_api_user_logout', methods: ['POST'])]
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
