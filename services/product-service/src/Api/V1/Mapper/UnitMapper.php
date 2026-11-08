<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Entity\Unit;

final readonly class UnitMapper
{
    public function __construct(
        private ProductMapper $productMapper
    ) {}
    public function toResource(Unit $unit): array
    {

        return [
            'id' => (int)$unit->getId(),
            'attributes' => [
                'code' => $unit->getCode(),
                'allowDecimal' => $unit->isAllowDecimal(),
                'products' => [array_map($this->productMapper->toResource(...), $unit->getProducts()->toArray())],
                'createdAt' => $unit->getCreatedAt()?->format(\DATE_ATOM),
                'updatedAt' => $unit->getUpdatedAt()?->format(\DATE_ATOM),
            ],
        ];
    }
}
