<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ErrorDocument')]
final readonly class ErrorDocument
{
    public function __construct(
        #[OA\Property(type: 'string', example: 'error')]
        public string $status,
        #[OA\Property(ref: new Model(type: ErrorDetail::class))]
        public ErrorDetail $error,
    ) {}
}

#[OA\Schema(schema: 'ErrorDetail')]
final readonly class ErrorDetail
{
    public function __construct(
        #[OA\Property(type: 'string', example: 'CONTACT_NOT_FOUND')]
        public string $code,
        #[OA\Property(type: 'string', example: 'Contact 01a1d2b2-3e94-7041-85c7-15155db7a457 not found.')]
        public string $message,
    ) {}
}
