<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\InvoiceType;
use App\Exceptions\NumberGenerationException;
use App\Repository\InvoiceRepository;
use DateTimeImmutable;
use Symfony\Component\Lock\LockFactory;

final readonly class InvoiceNumberGenerator
{
    public function __construct(
        private InvoiceRepository $invoices,
        private LockFactory $lockFactory,
    ) {}

    public function next(InvoiceType $type, ?DateTimeImmutable $today = null): string
    {
        $year = (int)($today ?? new DateTimeImmutable('today'))->format('Y');
        $highest = $this->invoices->highestNumberFor($type, $year);

        $sequence = $highest === null ? 1 : ((int)substr($highest, (int)strrpos($highest, '-') + 1)) + 1;

        return sprintf('%s-%d-%06d', $type->numberPrefix(), $year, $sequence);
    }

    /**
     * Runs the callback while holding the per-type/year lock, so the number it
     * hands over cannot be taken by a concurrent issue.
     */
    public function locked(InvoiceType $type, DateTimeImmutable $today, callable $callback): mixed
    {
        $year = (int)$today->format('Y');
        $lock = $this->lockFactory->createLock(sprintf('invoice-number-%s-%d', $type->value, $year), 30.0, true);

        if (!$lock->acquire(true)) {
            throw new NumberGenerationException('The invoice-number lock could not be acquired.');
        }

        try {
            return $callback($this->next($type, $today));
        } finally {
            $lock->release();
        }
    }
}
