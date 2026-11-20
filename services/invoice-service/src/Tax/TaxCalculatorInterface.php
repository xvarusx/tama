<?php

declare(strict_types=1);

namespace App\Tax;

use App\Exceptions\TaxServiceUnavailableException;

interface TaxCalculatorInterface
{
    /**
     * @param list<TaxLineInput> $lines
     *
     * @return list<TaxLineResult> one result per input line, in the same order
     *
     * @throws TaxServiceUnavailableException when the Tax Service cannot answer or answers with an unusable shape
     */
    public function calculate(string $currency, array $lines): array;
}
