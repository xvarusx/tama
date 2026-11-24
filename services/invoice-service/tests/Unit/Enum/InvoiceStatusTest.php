<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\InvoiceStatus;
use PHPUnit\Framework\TestCase;

final class InvoiceStatusTest extends TestCase
{
    public function testAllowedTransitions(): void
    {
        self::assertTrue(InvoiceStatus::DRAFT->canTransitionTo(InvoiceStatus::ISSUED));
        self::assertTrue(InvoiceStatus::DRAFT->canTransitionTo(InvoiceStatus::VOIDED));
        self::assertTrue(InvoiceStatus::ISSUED->canTransitionTo(InvoiceStatus::PARTIALLY_PAID));
        self::assertTrue(InvoiceStatus::ISSUED->canTransitionTo(InvoiceStatus::PAID));
        self::assertTrue(InvoiceStatus::ISSUED->canTransitionTo(InvoiceStatus::VOIDED));
        self::assertTrue(InvoiceStatus::PARTIALLY_PAID->canTransitionTo(InvoiceStatus::PAID));
    }

    public function testForbiddenTransitions(): void
    {
        self::assertFalse(InvoiceStatus::DRAFT->canTransitionTo(InvoiceStatus::PAID));
        self::assertFalse(InvoiceStatus::PAID->canTransitionTo(InvoiceStatus::ISSUED));
        self::assertFalse(InvoiceStatus::VOIDED->canTransitionTo(InvoiceStatus::DRAFT));
        self::assertFalse(InvoiceStatus::PAID->canTransitionTo(InvoiceStatus::VOIDED));
        self::assertFalse(InvoiceStatus::PARTIALLY_PAID->canTransitionTo(InvoiceStatus::VOIDED));
    }

    public function testFinalStates(): void
    {
        self::assertTrue(InvoiceStatus::PAID->isFinal());
        self::assertTrue(InvoiceStatus::VOIDED->isFinal());
        self::assertFalse(InvoiceStatus::DRAFT->isFinal());
        self::assertFalse(InvoiceStatus::ISSUED->isFinal());
        self::assertFalse(InvoiceStatus::PARTIALLY_PAID->isFinal());
    }
}
