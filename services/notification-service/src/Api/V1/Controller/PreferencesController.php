<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Documentation\ErrorDocument;
use App\Api\V1\Documentation\PreferenceEntryDocument;
use App\Api\V1\Mapper\PreferenceMapper;
use App\Api\V1\Request\SavePreferencesRequest;
use App\Entity\NotificationPreference;
use App\Repository\NotificationPreferenceRepository;
use App\Security\CallerResolver;
use App\Utils\ApiResponse;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/preferences', name: 'api_v1_preference_')]
#[OA\Tag(name: 'Preferences')]
final class PreferencesController extends AbstractController
{
    public function __construct(
        private readonly PreferenceMapper $mapper,
        private readonly NotificationPreferenceRepository $preferences,
        private readonly CallerResolver $callers,
        private readonly EntityManagerInterface $em,
    ) {}

    /** Matrix of every template type x channel, defaulting to enabled. */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'The caller\'s preference matrix.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: new Model(type: PreferenceEntryDocument::class))),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 401, description: 'Authentication required.', content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)))]
    public function list(Request $request): JsonResponse
    {
        $caller = $this->callers->resolve($request);

        return new JsonResponse(ApiResponse::success($this->mapper->matrix($caller->id)));
    }

    /** Upsert preference rows for the caller and return the refreshed matrix. */
    #[Route('', name: 'save', methods: ['PUT'])]
    #[OA\Response(response: 200, description: 'The refreshed preference matrix.')]
    #[OA\Response(response: 401, description: 'Authentication required.', content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)))]
    #[OA\Response(response: 422, description: 'Validation failed.')]
    public function save(Request $request, #[MapRequestPayload] SavePreferencesRequest $payload): JsonResponse
    {
        $caller = $this->callers->resolve($request);

        foreach ($payload->preferences as $entry) {
            $preference = $this->preferences->findOneBy([
                'userId' => $caller->id,
                'type' => $entry->type,
                'channel' => $entry->channel,
            ]);
            if ($preference === null) {
                $preference = new NotificationPreference(Uuid::fromString($caller->id), $entry->type, $entry->channel, $entry->enabled);
                $this->em->persist($preference);
            } else {
                $preference->setEnabled($entry->enabled);
            }
        }
        $this->em->flush();

        return new JsonResponse(ApiResponse::success($this->mapper->matrix($caller->id)));
    }
}
