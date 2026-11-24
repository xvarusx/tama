<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Documentation\ErrorDocument;
use App\Api\V1\Documentation\TemplateDocument;
use App\Api\V1\Mapper\TemplateMapper;
use App\Api\V1\Request\SaveTemplateRequest;
use App\Entity\NotificationTemplate;
use App\Exceptions\TemplateAlreadyExists;
use App\Exceptions\TemplateNotFound;
use App\Repository\NotificationTemplateRepository;
use App\Security\CallerResolver;
use App\Utils\ApiResponse;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/templates', name: 'api_v1_admin_template_')]
#[OA\Tag(name: 'Admin Templates')]
final class AdminTemplatesController extends AbstractController
{
    public function __construct(
        private readonly NotificationTemplateRepository $templates,
        private readonly TemplateMapper $mapper,
        private readonly CallerResolver $callers,
        private readonly EntityManagerInterface $em,
    ) {}

    /** List every template. */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'All templates.', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: TemplateDocument::class))))]
    #[OA\Response(response: 403, description: 'Administrator role required.', content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)))]
    public function list(Request $request): JsonResponse
    {
        $this->callers->requireAdmin($this->callers->resolve($request));

        return new JsonResponse(ApiResponse::success(
            array_map($this->mapper->toResource(...), $this->templates->findAll()),
        ));
    }

    /** Create a template. The (type, channel, locale) triple is the natural key. */
    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Template created.')]
    #[OA\Response(response: 409, description: 'The natural key is already taken.', content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)))]
    public function create(Request $request, #[MapRequestPayload] SaveTemplateRequest $payload): JsonResponse
    {
        $this->callers->requireAdmin($this->callers->resolve($request));
        $template = new NotificationTemplate(
            $payload->type,
            $payload->channel,
            $payload->locale,
            $payload->subject,
            $payload->bodyTemplate,
        );
        $this->em->persist($template);
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new TemplateAlreadyExists($payload->type, $payload->channel->value, $payload->locale);
        }

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($template)), Response::HTTP_CREATED);
    }

    /** Read one template by its UUID. */
    #[Route('/{id}', name: 'show', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The requested template.', content: new OA\JsonContent(ref: new Model(type: TemplateDocument::class)))]
    #[OA\Response(response: 404, description: 'Template not found.', content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)))]
    public function show(string $id, Request $request): JsonResponse
    {
        $this->callers->requireAdmin($this->callers->resolve($request));
        $template = $this->templateOr404($id);

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($template)));
    }

    /** Replace the subject and body template. The natural key is immutable. */
    #[Route('/{id}', name: 'update', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['PUT'])]
    #[OA\Response(response: 200, description: 'Template updated.')]
    public function update(string $id, Request $request, #[MapRequestPayload] SaveTemplateRequest $payload): JsonResponse
    {
        $this->callers->requireAdmin($this->callers->resolve($request));
        $template = $this->templateOr404($id);
        $template->update($payload->subject, $payload->bodyTemplate);
        $this->em->flush();

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($template)));
    }

    /** Delete a template. */
    #[Route('/{id}', name: 'delete', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Template deleted.')]
    public function delete(string $id, Request $request): Response
    {
        $this->callers->requireAdmin($this->callers->resolve($request));
        $template = $this->templateOr404($id);
        $this->em->remove($template);
        $this->em->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function templateOr404(string $id): NotificationTemplate
    {
        $uuid = Uuid::fromString($id);

        return $this->templates->findById($uuid) ?? throw new TemplateNotFound($uuid);
    }
}
