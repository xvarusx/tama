<?php

declare(strict_types=1);

namespace App\Tax;

use App\Exceptions\TaxServiceUnavailableException;
use JsonException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsAlias(TaxCalculatorInterface::class)]
final readonly class HttpTaxCalculator implements TaxCalculatorInterface
{
    public function __construct(
        private HttpClientInterface $http,
        #[Autowire(env: 'TAX_SERVICE_BASE_URL')]
        private string $baseUrl,
        #[Autowire('%env(float:TAX_SERVICE_TIMEOUT)%')]
        private float $timeout,
    ) {}

    public function calculate(string $currency, array $lines): array
    {
        $body = [
            'currency' => $currency,
            'lines' => array_map(
                static fn(TaxLineInput $line): array => [
                    'productId' => $line->productId->toRfc4122(),
                    'quantity' => $line->quantity,
                    'unitPrice' => $line->unitPrice,
                ],
                $lines,
            ),
        ];

        try {
            $response = $this->http->request('POST', rtrim($this->baseUrl, '/') . '/api/v1/tax/calculate', [
                'json' => $body,
                'timeout' => $this->timeout,
            ]);
            $status = $response->getStatusCode();
        } catch (HttpClientException $exception) {
            throw new TaxServiceUnavailableException('The Tax Service could not be reached: ' . $exception->getMessage());
        }

        if ($status >= 400) {
            throw new TaxServiceUnavailableException(sprintf('The Tax Service answered with HTTP %d.', $status));
        }

        try {
            $data = $response->toArray(false);
        } catch (HttpClientException|JsonException) {
            throw new TaxServiceUnavailableException('The Tax Service returned an unreadable body.');
        }

        $results = $data['lines'] ?? null;
        if (!\is_array($results) || \count($results) !== \count($lines)) {
            throw new TaxServiceUnavailableException('The Tax Service did not return one result per line.');
        }

        $calculated = [];
        foreach (array_values($results) as $result) {
            if (!\is_array($result)) {
                throw new TaxServiceUnavailableException('The Tax Service returned a malformed line result.');
            }

            $rate = $result['taxRateBps'] ?? null;
            $amount = $result['taxAmount'] ?? null;
            if (!\is_int($rate) || $rate < 0 || $rate > 10000 || !\is_int($amount) || $amount < 0) {
                throw new TaxServiceUnavailableException('The Tax Service returned invalid tax values.');
            }

            $calculated[] = new TaxLineResult($rate, $amount);
        }

        return $calculated;
    }
}
