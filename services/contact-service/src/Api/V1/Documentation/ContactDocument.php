<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'ContactDocument')]
final readonly class ContactDocument
{
    public function __construct(
        #[OA\Property(type: 'string', format: 'uuid', example: '01998f8c-0a6b-7c1d-9a0b-2f6a0ad3d54e')]
        public string $id,
        #[OA\Property(type: 'string', example: 'contact')]
        public string $type,
        #[OA\Property(ref: new Model(type: ContactAttributes::class))]
        public ContactAttributes $attributes,
    ) {}
}

#[OA\Schema(schema: 'ContactAttributes')]
final readonly class ContactAttributes
{
    public function __construct(
        #[OA\Property(type: 'string', example: 'Acme Supplies')]
        public string $name,
        #[OA\Property(type: 'string', format: 'email', nullable: true, example: 'contact@acme.example')]
        public ?string $email,
        #[OA\Property(type: 'string', nullable: true, example: '+33 1 23 45 67 89')]
        public ?string $phone,
        #[OA\Property(type: 'string', nullable: true, example: '123 Main St')]
        public ?string $address,
        #[OA\Property(type: 'string', nullable: true, example: 'FR12345678901')]
        public ?string $taxNumber,
        #[OA\Property(type: 'string', enum: ['client', 'supplier'], example: 'client')]
        public string $contactType,
        #[OA\Property(type: 'string', enum: ['active', 'inactive', 'blacklisted'], example: 'active')]
        public string $status,
        #[OA\Property(type: 'string', nullable: true, example: 'Repeated late payments')]
        public ?string $blacklistReason,
        #[OA\Property(type: 'string', format: 'date-time', nullable: true, example: '2026-11-13T02:00:00+00:00')]
        public ?string $blacklistedAt,
        #[OA\Property(type: 'string', format: 'uuid', nullable: true, example: '01998f8c-0a6b-7c1d-9a0b-2f6a0ad3d54e')]
        public ?string $blacklistedBy,
        #[OA\Property(type: 'string', format: 'date-time', example: '2026-11-13T02:00:00+00:00')]
        public string $createdAt,
        #[OA\Property(type: 'string', format: 'date-time', nullable: true, example: '2026-11-13T03:00:00+00:00')]
        public ?string $updatedAt,
        #[OA\Property(type: 'string', format: 'uuid', example: '01998f8c-0a6b-7c1d-9a0b-2f6a0ad3d54e')]
        public string $createdBy,
        #[OA\Property(type: 'string', format: 'uuid', nullable: true, example: '01998f8c-0a6b-7c1d-9a0b-2f6a0ad3d54e')]
        public ?string $updatedBy,
    ) {}
}
