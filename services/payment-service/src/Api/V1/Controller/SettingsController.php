<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Documentation\SettingDocument;
use App\Api\V1\Mapper\SettingMapper;
use App\Api\V1\Request\UpdateSettingRequest;
use App\Repository\SettingRepository;
use App\Utils\ApiResponse;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/settings', name: 'api_v1_setting_')]
#[OA\Tag(name: 'Settings')]
final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly SettingRepository $settings,
        private readonly SettingMapper $mapper,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of settings.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: SettingDocument::class)),
                ),
            ],
            type: 'object',
        ),
    )]
    public function list(): JsonResponse
    {
        $items = $this->settings->findAll();
        $data = array_map(fn($setting) => [
            'key' => $setting->getKey(),
            'attributes' => [
                'value' => $setting->getValue(),
                'type' => $setting->getType(),
                'description' => $setting->getDescription(),
            ],
        ], $items);

        return new JsonResponse(ApiResponse::success($data));
    }

    #[Route('/{key}', name: 'show', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'The requested setting.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: SettingDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Setting not found.')]
    public function show(string $key): JsonResponse
    {
        $setting = $this->settings->find($key) 
            ?? throw $this->createNotFoundException('Setting not found.');

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($setting)));
    }

    #[Route('', name: 'upsert', methods: ['PUT', 'POST'])]
    #[OA\Response(
        response: 200,
        description: 'Setting created or updated.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: SettingDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 422, description: 'Validation failed.')]
    public function upsert(#[MapRequestPayload] UpdateSettingRequest $payload): JsonResponse
    {
        // This would normally use UpdateSettingHandler
        $setting = $this->settings->find($payload->key);

        if ($setting === null) {
            $setting = new \App\Entity\Setting(
                key: $payload->key,
                value: $payload->value,
                type: 'STRING',
                description: $payload->description,
            );
            $this->em->persist($setting);
            $this->em->flush();
        } else {
            $setting->setValue($payload->value);
            if ($payload->description !== null) {
                $setting->setDescription($payload->description);
            }
            $this->em->persist($setting);
            $this->em->flush();
        }

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($setting)));
    }
}