<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax;

use App\Exceptions\TaxServiceUnavailableException;
use App\Tax\HttpTaxCalculator;
use App\Tax\TaxLineInput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Uid\Uuid;

final class HttpTaxCalculatorTest extends TestCase
{
    public function testMapsTaxResults(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://tax.test/api/v1/tax/calculate', $url);
            $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);
            self::assertSame('EUR', $body['currency']);
            self::assertSame(2, $body['lines'][0]['quantity']);
            self::assertSame(1000, $body['lines'][0]['unitPrice']);

            return new MockResponse(
                json_encode(['lines' => [['taxRateBps' => 2000, 'taxAmount' => 400]]], \JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });
        $calculator = new HttpTaxCalculator($client, 'http://tax.test', 3.0);

        $result = $calculator->calculate('EUR', [new TaxLineInput(Uuid::v7(), 2, 1000)]);
        self::assertCount(1, $result);
        self::assertSame(2000, $result[0]->taxRateBps);
        self::assertSame(400, $result[0]->taxAmount);
    }

    public function testServerErrorBecomesUnavailable(): void
    {
        $calculator = new HttpTaxCalculator(
            new MockHttpClient(new MockResponse('{}', ['http_code' => 500])),
            'http://tax.test',
            3.0,
        );

        $this->expectException(TaxServiceUnavailableException::class);
        $calculator->calculate('EUR', [new TaxLineInput(Uuid::v7(), 1, 1000)]);
    }

    public function testMalformedLineCountIsRejected(): void
    {
        $calculator = new HttpTaxCalculator(
            new MockHttpClient(new MockResponse(json_encode(['lines' => []], \JSON_THROW_ON_ERROR), ['http_code' => 200])),
            'http://tax.test',
            3.0,
        );

        $this->expectException(TaxServiceUnavailableException::class);
        $calculator->calculate('EUR', [new TaxLineInput(Uuid::v7(), 1, 1000)]);
    }

    public function testMissingTaxAmountIsRejected(): void
    {
        $calculator = new HttpTaxCalculator(
            new MockHttpClient(new MockResponse(
                json_encode(['lines' => [['taxRateBps' => 2000]]], \JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            )),
            'http://tax.test',
            3.0,
        );

        $this->expectException(TaxServiceUnavailableException::class);
        $calculator->calculate('EUR', [new TaxLineInput(Uuid::v7(), 1, 1000)]);
    }

    public function testInvalidJsonIsRejected(): void
    {
        $calculator = new HttpTaxCalculator(
            new MockHttpClient(new MockResponse('not-json', ['http_code' => 200])),
            'http://tax.test',
            3.0,
        );

        $this->expectException(TaxServiceUnavailableException::class);
        $calculator->calculate('EUR', [new TaxLineInput(Uuid::v7(), 1, 1000)]);
    }

    public function testOutOfRangeRateIsRejected(): void
    {
        $calculator = new HttpTaxCalculator(
            new MockHttpClient(new MockResponse(
                json_encode(['lines' => [['taxRateBps' => 10001, 'taxAmount' => 100]]], \JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            )),
            'http://tax.test',
            3.0,
        );

        $this->expectException(TaxServiceUnavailableException::class);
        $calculator->calculate('EUR', [new TaxLineInput(Uuid::v7(), 1, 1000)]);
    }
}
