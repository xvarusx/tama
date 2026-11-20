<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Documentation\ContactDocument;
use App\Api\V1\Documentation\ErrorDocument;
use App\Api\V1\Mapper\ContactMapper;
use App\Api\V1\Resource\ContactResource;
use App\Dto\BlacklistContactInput;
use App\Entity\Contact;
use App\Entity\ContactAuditEntry;
use App\Entity\OutboxEvent;
use App\Enum\ContactStatus;
use App\Event\ContactBlacklisted;
use App\Event\ContactDeactivated;
use App\Event\ContactReinstated;
use App\Security\ContactUser;
use App\Utils\ApiResponse;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * State changes of a contact (client or supplier): blacklist, deactivate, reinstate, activate.
 * The business rules live in the Contact entity; this controller only wires HTTP to it.
 */
#[OA\Tag(name: 'Contact statuses', description: 'Contact state transitions: blacklist, reinstate, deactivate, activate')]
#[Route('/contacts/{id}', requirements: ['id' => Requirement::UUID], name: 'api_v1_contact_status_')]
final class ContactStatusController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TagAwareCacheInterface $cache,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ContactMapper $mapper,
    ) {}

    #[Route('/blacklist', name: 'app_api_contact_blacklist', methods: ['POST'])]
    #[OA\Post(
        operationId: 'blacklistContact',
        tags: ['Contact statuses'],
        summary: 'Blacklist a contact',
        parameters: [
            new OA\Parameter(name: 'id', description: 'Contact ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '01a1d2b2-3e94-7041-85c7-15155db7a457')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: BlacklistContactInput::class)),
        ),
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Contact blacklisted',
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
                response: Response::HTTP_CONFLICT,
                description: 'Transition not allowed or stale contact',
                content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)),
            ),
        ],
    )]
    public function blacklist(Contact $contact, #[MapRequestPayload] BlacklistContactInput $input): JsonResponse
    {
        $userId = Uuid::fromString($this->currentUserId());

        return $this->apply($contact, $userId, 'blacklisted', fn() => $contact->blacklist($input->reason, $userId));
    }

    #[Route('/reinstate', name: 'app_api_contact_reinstate', methods: ['POST'])]
    #[OA\Post(
        operationId: 'reinstateContact',
        tags: ['Contact statuses'],
        summary: 'Reinstate a blacklisted contact',
        parameters: [
            new OA\Parameter(name: 'id', description: 'Contact ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '01a1d2b2-3e94-7041-85c7-15155db7a457')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Contact reinstated',
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
                response: Response::HTTP_CONFLICT,
                description: 'Transition not allowed or stale contact',
                content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)),
            ),
        ],
    )]
    public function reinstate(Contact $contact): JsonResponse
    {
        $userId = Uuid::fromString($this->currentUserId());

        return $this->apply($contact, $userId, 'reinstated', fn() => $contact->reinstate($userId));
    }

    #[Route('/deactivate', name: 'app_api_contact_deactivate', methods: ['POST'])]
    #[OA\Post(
        operationId: 'deactivateContact',
        tags: ['Contact statuses'],
        summary: 'Deactivate a contact',
        parameters: [
            new OA\Parameter(name: 'id', description: 'Contact ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '01a1d2b2-3e94-7041-85c7-15155db7a457')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Contact deactivated',
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
                response: Response::HTTP_CONFLICT,
                description: 'Transition not allowed or stale contact',
                content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)),
            ),
        ],
    )]
    public function deactivate(Contact $contact): JsonResponse
    {
        $userId = Uuid::fromString($this->currentUserId());

        return $this->apply($contact, $userId, 'deactivated', fn() => $contact->deactivate($userId));
    }

    #[Route('/activate', name: 'app_api_contact_activate', methods: ['POST'])]
    #[OA\Post(
        operationId: 'activateContact',
        tags: ['Contact statuses'],
        summary: 'Activate an inactive contact',
        parameters: [
            new OA\Parameter(name: 'id', description: 'Contact ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '01a1d2b2-3e94-7041-85c7-15155db7a457')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Contact activated',
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
                response: Response::HTTP_CONFLICT,
                description: 'Transition not allowed or stale contact',
                content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)),
            ),
        ],
    )]
    public function activate(Contact $contact): JsonResponse
    {
        $userId = Uuid::fromString($this->currentUserId());

        return $this->apply($contact, $userId, 'activated', fn() => $contact->activate($userId));
    }

    /**
     * Runs a domain action, saves it, clears the list cache and publishes the domain events.
     */
    private function apply(Contact $contact, Uuid $userId, string $change, \Closure $action): JsonResponse
    {
        try {
            $action();
            $this->em->persist(new ContactAuditEntry($contact->getId(), $userId, $change));

            foreach ($contact->pullEvents() as $event) {
                $this->em->persist(OutboxEvent::fromEvent($event));
            }

            $this->em->flush();
        } catch (\DomainException $e) {
            // A rule of the entity was broken (e.g. reinstating a contact that is not blacklisted).
            throw new ConflictHttpException($e->getMessage(), $e);
        } catch (OptimisticLockException $e) {
            throw new ConflictHttpException('The contact was changed by someone else, reload it and try again.', $e);
        }

        // The filtered list (GET /api/contact) is cached by tag, so it must be cleared after a change.
        $this->cache->invalidateTags(['contact']);

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($contact)), Response::HTTP_OK);
    }

    private function currentUserId(): string
    {
        $user = $this->getUser();
        if (!$user instanceof ContactUser) {
            throw $this->createAccessDeniedException('Authentication is required.');
        }

        return $user->getId();
    }
}
