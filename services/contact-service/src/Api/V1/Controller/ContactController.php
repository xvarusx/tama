<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Documentation\ContactDocument;
use App\Api\V1\Documentation\ErrorDocument;
use App\Api\V1\Documentation\PaginationDocument;
use App\Api\V1\Mapper\ContactMapper;
use App\Api\V1\Request\CreateContactRequest;
use App\Api\V1\Request\ListContactsQuery;
use App\Api\V1\Request\UpdateContactRequest;
use App\Handler\Contact\CreateContactHandler;
use App\Handler\Contact\UpdateContactHandler;
use App\Repository\ContactRepository;
use App\Utils\ApiResponse;
use App\Utils\Pagination;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/contacts', name: 'api_v1_contact_')]
final class ContactController extends AbstractController
{
    public function __construct(
        private readonly TagAwareCacheInterface $cache,
        private readonly ContactRepository $contactRepository,
        private readonly ContactMapper $mapper,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    #[OA\Get(
        summary: 'List contacts with search and filters',
        parameters: [
            new OA\Parameter(name: 'search', description: 'Search by name or phone', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'type', description: 'Filter by contact type', in: 'query', schema: new OA\Schema(type: 'string', enum: ['client', 'supplier'])),
            new OA\Parameter(name: 'status', description: 'Filter by contact status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['active', 'inactive', 'blacklisted'])),
            new OA\Parameter(name: 'page', description: 'Page number', in: 'query', schema: new OA\Schema(type: 'integer', default: '1')),
            new OA\Parameter(name: 'pageSize', description: 'Items per page', in: 'query', schema: new OA\Schema(type: 'integer', default: '20', minimum: 1, maximum: 100)),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Successful response',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: new Model(type: ContactDocument::class))),
                        new OA\Property(property: 'pagination', ref: new Model(type: PaginationDocument::class)),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: Response::HTTP_BAD_REQUEST,
                description: 'Invalid query parameters',
                content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)),
            ),
        ],
    )]
    public function index(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListContactsQuery $query,
        UrlGeneratorInterface $urlGenerator,
    ): JsonResponse {
        $result = $this->contactRepository->findPaginated(
            $query->page,
            $query->pageSize,
            $query->search,
            $query->type,
            $query->status,
        );

        $items = array_map($this->mapper->toResource(...), $result['items']);

        $pagination = Pagination::create(
            $query->page,
            $query->pageSize,
            $result['total'],
            fn(int $page) => $urlGenerator->generate('api_v1_contact_index', array_merge(array_filter(get_object_vars($query), fn($value) => $value !== null), ['page' => $page]), UrlGeneratorInterface::ABSOLUTE_URL),
        );

        return new JsonResponse(ApiResponse::success($items, $pagination), Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    #[OA\Get(
        summary: 'Get a contact by ID',
        parameters: [
            new OA\Parameter(name: 'id', description: 'Contact ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Successful response',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'data', ref: new Model(type: ContactDocument::class)),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Contact not found',
                content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)),
            ),
        ],
    )]
    public function show(string $id, ContactRepository $repository, ContactMapper $mapper): JsonResponse
    {
        $contact = $repository->find(Uuid::fromString($id));

        if (!$contact) {
            throw new \App\Exceptions\ContactNotFoundException((string) $id);
        }

        return new JsonResponse(ApiResponse::success($mapper->toResource($contact)), Response::HTTP_OK);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Create a new contact',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: CreateContactRequest::class)),
        ),
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Contact created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'data', ref: new Model(type: ContactDocument::class)),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: Response::HTTP_BAD_REQUEST,
                description: 'Invalid input',
                content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)),
            ),
        ],
    )]
    public function create(
        #[MapRequestPayload]
        CreateContactRequest $payload,
        CreateContactHandler $handler,
    ): JsonResponse {
        $userId = Uuid::fromString($this->getCurrentUserId());
        $contact = $handler($payload, $userId);

        $this->cache->invalidateTags(['contact']);

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($contact)), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID], methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        summary: 'Update a contact',
        parameters: [
            new OA\Parameter(name: 'id', description: 'Contact ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: UpdateContactRequest::class)),
        ),
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Contact updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'data', ref: new Model(type: ContactDocument::class)),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Contact not found',
                content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)),
            ),
            new OA\Response(
                response: Response::HTTP_BAD_REQUEST,
                description: 'Invalid input',
                content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)),
            ),
        ],
    )]
    public function update(
        string $id,
        #[MapRequestPayload]
        UpdateContactRequest $payload,
        UpdateContactHandler $handler,
    ): JsonResponse {
        $userId = Uuid::fromString($this->getCurrentUserId());
        $contact = $handler(Uuid::fromString($id), $payload, $userId);

        $this->cache->invalidateTags(['contact']);

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($contact)), Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Delete a contact',
        parameters: [
            new OA\Parameter(name: 'id', description: 'Contact ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Contact deleted'),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Contact not found',
                content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)),
            ),
        ],
    )]
    public function delete(string $id, EntityManagerInterface $em): Response
    {
        $contact = $em->find(\App\Entity\Contact::class, Uuid::fromString($id));

        if (!$contact) {
            throw new \App\Exceptions\ContactNotFoundException($id);
        }

        $userId = Uuid::fromString($this->getCurrentUserId());
        $em->persist(new \App\Entity\ContactAuditEntry($contact->getId(), $userId, 'deleted'));
        $em->remove($contact);
        $em->flush();

        $this->cache->invalidateTags(['contact']);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function getCurrentUserId(): string
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Security\ContactUser) {
            throw $this->createAccessDeniedException('Authentication is required.');
        }

        return $user->getId();
    }
}
