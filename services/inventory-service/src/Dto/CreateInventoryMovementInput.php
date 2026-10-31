<?php

namespace App\Dto;

use App\Enum\InventoryMovementType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class CreateInventoryMovementInput
{
    public function __construct(
        public InventoryMovementType $type,
        // Signed so that Adjustment can correct stock downwards.
        // The service enforces "> 0" for every other type.
        #[Assert\Type('numeric')]
        #[Assert\NotEqualTo(0)]
        #[Assert\Range(min: '-999999999.999', max: '999999999.999')]
        public float|string $quantity,
        #[Assert\Length(max: 32)]
        public ?string $referenceType = null,
        #[Assert\Length(max: 255)]
        public ?string $referenceId = null,
        #[Assert\Length(max: 1000)]
        public ?string $reason = null,
    ) {
    }

    #[Assert\Callback]
    public function validateReference(ExecutionContextInterface $context): void
    {
        if (null !== $this->referenceId && null === $this->referenceType) {
            $context->buildViolation('referenceType is required when referenceId is set.')
                ->atPath('referenceType')
                ->addViolation();
        }
    }
}
