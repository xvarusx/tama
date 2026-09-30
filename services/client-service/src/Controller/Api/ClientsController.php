<?php

namespace App\Controller\Api;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

final class ClientsController extends AbstractController
{
    #[Route('/api/clients', name: 'app_api_clients_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $client = $em->getRepository(Client::class)->findAll();

        return $this->json($client);
    }

    #[Route('/api/clients/{id}', name: 'app_api_clients_show', methods: ['GET'])]
    public function showById(EntityManagerInterface $em, int $id): JsonResponse
    {
        $client = $em->getRepository(Client::class)->find($id);

        if (null === $client) {
            throw $this->createNotFoundException('Client not found');
        }

        return $this->json($client);
    }

    #[Route('/api/clients', name: 'app_api_clients_create', methods: ['POST'])]
    public function create(EntityManagerInterface $em, Request $request, SerializerInterface $serializer): JsonResponse
    {
        $requestContent = $request->getContent();
        $client = $serializer->deserialize($requestContent, Client::class, 'json');
        $em->persist($client);
        $em->flush();

        return $this->json($client, 201);
    }

    #[Route('/api/clients/{id}', name: 'app_api_clients_update', methods: ['PUT', 'PATCH'])]
    public function update(EntityManagerInterface $em, Request $request, SerializerInterface $serializer, int $id): JsonResponse
    {
        $client = $em->getRepository(Client::class)->find($id);

        if (null === $client) {
            throw $this->createNotFoundException('Client not found');
        }

        $serializer->deserialize(
            $request->getContent(),
            Client::class,
            'json',
            ['object_to_populate' => $client]
        );
        $client->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->json($client);
    }

    #[Route('/api/clients/{id}', name: 'app_api_clients_delete', methods: ['DELETE'])]
    public function delete(EntityManagerInterface $em, Client $client): JsonResponse
    {
        $em->remove($client);
        $em->flush();

        return $this->json(null, 204);
    }

    private function ensureIsValidateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}
