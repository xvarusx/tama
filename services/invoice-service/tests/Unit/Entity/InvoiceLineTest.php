<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\InvoiceLine;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class InvoiceLineTest extends TestCase
{
    public function testLineTotalIncludesTax(): void
    {
        $line = new InvoiceLine(Uuid::v7(), 'Café beans', 2, 1000, 2000, 400);

        self::assertSame(2000, $line->getSubtotal());
        self::assertSame(2400, $line->getTotal());
    }

    public function testLineRejectsNonPositiveQuantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InvoiceLine(Uuid::v7(), 'Bad', 0, 1000, 2000, 0);
    }

    public function testLineRejectsNegativeUnitPrice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InvoiceLine(Uuid::v7(), 'Bad', 1, -1, 2000, 0);
    }

    public function testLineRejectsOutOfRangeTaxRate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InvoiceLine(Uuid::v7(), 'Bad', 1, 1000, 10001, 0);
    }

    public function testLineRejectsBlankDescription(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InvoiceLine(Uuid::v7(), '  ', 1, 1000, 2000, 0);
    }

    public function testPositionCanBeAssigned(): void
    {
        $line = new InvoiceLine(Uuid::v7(), 'Widget', 1, 1000, 2000, 200);
        $line->setPosition(3);

        self::assertSame(3, $line->getPosition());
    }
}
