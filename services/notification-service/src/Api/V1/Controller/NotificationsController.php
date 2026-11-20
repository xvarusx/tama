<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Documentation\ErrorDocument;
use App\Api\V1\Documentation\NotificationDocument;
use App\Api\V1\Documentation\PaginationDocument;
use App\Api\V1\Mapper\NotificationMapper;
use App\Api\V1\Request\ListNotificationsQuery;
use App\Dto\NotificationFilter;
use App\Entity\Notification;
use App\Exceptions\NotificationNotFound;
use App\Repository\NotificationRepository;
use App\Security\CallerResolver;
use App\Utils\ApiResponse;
use App\Utils\Pagination;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

#[Route('/notifications', name: 'api_v1_notification_')]
#[OA\Tag(name: 'Notifications')]
final class NotificationsController extends AbstractController
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly NotificationMapper $mapper,
        private readonly CallerResolver $callers,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Paginated inbox for the caller. Hidden (email-only) items are excluded and
     * the unread count returned in `meta` follows the same visibility rule.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Paginated inbox.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: new Model(type: NotificationDocument::class))),
                new OA\Property(property: 'pagination', ref: new Model(type: PaginationDocument::class)),
                new OA\Property(property: 'meta', type: 'object', example: ['unreadCount' => 3]),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 400, description: 'Invalid query parameters.')]
    #[OA\Response(response: 401, description: 'Authentication required.', content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)))]
    public function list(
        Request $request,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ListNotificationsQuery $query = new ListNotificationsQuery(),
    ): JsonResponse {
        $caller = $this->callers->resolve($request);
        $filter = new NotificationFilter(
            page: $query->page,
            pageSize: $query->pageSize,
            status: $query->status,
            type: $query->type,
            severity: $query->severity,
            search: $query->search,
        );

        [$items, $total] = $this->notifications->searchForRecipient($caller->id, $filter);
        $pagination = Pagination::create(
            $query->page,
            $query->pageSize,
            $total,
            fn(int $page): string => $this->generateUrl(
                'api_v1_notification_list',
                [...$request->query->all(), 'page' => $page],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        );

        return new JsonResponse(ApiResponse::success(
            array_map($this->mapper->toResource(...), $items),
            $pagination,
            ['unreadCount' => $this->notifications->countUnread($caller->id)],
        ));
    }

    /** Unread count for the header bell. */
    #[Route('/unread-count', name: 'unread_count', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The number of unread inbox items.')]
    #[OA\Response(response: 401, description: 'Authentication required.', content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)))]
    public function unreadCount(Request $request): JsonResponse
    {
        $caller = $this->callers->resolve($request);

        return new JsonResponse(ApiResponse::success(['count' => $this->notifications->countUnread($caller->id)]));
    }

    /** Mark every unread inbox item as read. */
    #[Route('/read-all', name: 'read_all', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Number of updated notifications.')]
    #[OA\Response(response: 401, description: 'Authentication required.', content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)))]
    public function readAll(Request $request): JsonResponse
    {
        $caller = $this->callers->resolve($request);

        return new JsonResponse(ApiResponse::success(['updated' => $this->notifications->markAllRead($caller->id)]));
    }

    /** Archive every read inbox item. */
    #[Route('/archive-read', name: 'archive_read', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Number of updated notifications.')]
    #[OA\Response(response: 401, description: 'Authentication required.', content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)))]
    public function archiveRead(Request $request): JsonResponse
    {
        $caller = $this->callers->resolve($request);

        return new JsonResponse(ApiResponse::success(['updated' => $this->notifications->archiveAllRead($caller->id)]));
    }

    /** One inbox item, visible to its owner only. */
    #[Route('/{id}', name: 'show', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The requested notification.', content: new OA\JsonContent(ref: new Model(type: NotificationDocument::class)))]
    #[OA\Response(response: 404, description: 'Notification not found.', content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)))]
    public function show(string $id, Request $request): JsonResponse
    {
        $caller = $this->callers->resolve($request);
        $notification = $this->visibleForRecipient($caller->id, $id);

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($notification)));
    }

    /** Idempotent mark-as-read. Archived notifications conflict. */
    #[Route('/{id}/read', name: 'read', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['POST'])]
    #[OA\Response(response: 200, description: 'The updated notification.', content: new OA\JsonContent(ref: new Model(type: NotificationDocument::class)))]
    #[OA\Response(response: 409, description: 'The notification is archived.', content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)))]
    public function markRead(string $id, Request $request): JsonResponse
    {
        $caller = $this->callers->resolve($request);
        $notification = $this->visibleForRecipient($caller->id, $id);
        $notification->markAsRead();
        $this->em->flush();

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($notification)));
    }

    /** Idempotent archive. */
    #[Route('/{id}/archive', name: 'archive', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['POST'])]
    #[OA\Response(response: 200, description: 'The updated notification.', content: new OA\JsonContent(ref: new Model(type: NotificationDocument::class)))]
    public function archive(string $id, Request $request): JsonResponse
    {
        $caller = $this->callers->resolve($request);
        $notification = $this->visibleForRecipient($caller->id, $id);
        $notification->archive();
        $this->em->flush();

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($notification)));
    }

    private function visibleForRecipient(Uuid|string $recipientId, string $id): Notification
    {
        $notification = $this->notifications->findForRecipient($recipientId, Uuid::fromString($id));
        if ($notification === null || !$notification->isVisibleInApp()) {
            throw new NotificationNotFound($recipientId, Uuid::fromString($id));
        }

        return $notification;
    }
}
