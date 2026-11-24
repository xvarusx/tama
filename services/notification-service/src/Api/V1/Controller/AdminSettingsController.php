<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Documentation\ErrorDocument;
use App\Api\V1\Documentation\SettingDocument;
use App\Api\V1\Mapper\SettingMapper;
use App\Api\V1\Request\UpdateSettingRequest;
use App\Exceptions\SettingNotFound;
use App\Repository\SettingRepository;
use App\Security\CallerResolver;
use App\Service\SettingProvider;
use App\Utils\ApiResponse;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/settings', name: 'api_v1_admin_setting_')]
#[OA\Tag(name: 'Admin Settings')]
final class AdminSettingsController extends AbstractController
{
    /** Numeric settings must be positive integers; other keys accept any string. */
    private const array NUMERIC_KEYS = [
        'notifications.retention_days',
        'notifications.max_attempts',
        'notifications.retry_base_seconds',
        'notifications.retry_max_seconds',
        'notifications.sending_timeout_seconds',
    ];

    public function __construct(
        private readonly SettingRepository $settings,
        private readonly SettingMapper $mapper,
        private readonly CallerResolver $callers,
        private readonly SettingProvider $settingProvider,
        private readonly EntityManagerInterface $em,
    ) {}

    /** List all settings. */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'All settings.', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: SettingDocument::class))))]
    #[OA\Response(response: 403, description: 'Administrator role required.', content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)))]
    public function list(Request $request): JsonResponse
    {
        $this->callers->requireAdmin($this->callers->resolve($request));

        return new JsonResponse(ApiResponse::success(
            array_map($this->mapper->toResource(...), $this->settings->all()),
        ));
    }

    /** Update one known setting by key. */
    #[Route('/{key}', name: 'update', requirements: ['key' => '[A-Za-z0-9._-]+'], methods: ['PUT'])]
    #[OA\Response(response: 200, description: 'The updated setting.', content: new OA\JsonContent(ref: new Model(type: SettingDocument::class)))]
    #[OA\Response(response: 404, description: 'Unknown setting key.', content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)))]
    #[OA\Response(response: 422, description: 'Validation failed.')]
    public function update(string $key, Request $request, #[MapRequestPayload] UpdateSettingRequest $payload): JsonResponse
    {
        $this->callers->requireAdmin($this->callers->resolve($request));
        $setting = $this->settings->findByKey($key) ?? throw new SettingNotFound($key);

        if (\in_array($key, self::NUMERIC_KEYS, true)
            && (filter_var($payload->value, FILTER_VALIDATE_INT) === false || (int)$payload->value <= 0)
        ) {
            return new JsonResponse([
                'status' => 'error',
                'error' => [
                    'code' => 'INVALID_SETTING_VALUE',
                    'message' => sprintf('Setting "%s" must be a positive integer.', $key),
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $setting->setValue($payload->value);
        $this->em->flush();
        $this->settingProvider->invalidate();

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($setting)));
    }
}
