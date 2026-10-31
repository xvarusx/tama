<?php

namespace App\Service;

use App\Entity\InventoryMovement;

final readonly class AppliedMovement
{
    public function __construct(
        public InventoryMovement $movement,
        /** false when the movement was a replay of an already-recorded reference */
        public bool $created,
    ) {
    }
}
