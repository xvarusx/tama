<?php

declare(strict_types=1);

namespace App\Tests\Api\V1\Request;

use App\Api\V1\Request\CreatePaymentPlanRequest;
use App\Api\V1\Request\InstallmentRequest;
use Symfony\Component\Validator\Validation;
use PHPUnit\Framework\TestCase;

final class CreatePaymentPlanRequestTest extends TestCase
{
    private $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidator();
    }

    public function test_valid_request_passes_validation(): void
    {
        $request = new CreatePaymentPlanRequest(
            invoiceId: 'inv-123',
            totalAmount: 10000, // 100.00 in cents
            currency: 'USD',
            installments: [
                new InstallmentRequest('2024-01-15', 5000),
                new InstallmentRequest('2024-02-15', 5000),
            ]
        );

        $violations = $this->validator->validate($request);

        $this->assertCount(0, $violations);
    }

    public function test_invoice_id_is_required(): void
    {
        $request = new CreatePaymentPlanRequest(
            invoiceId: '',
            totalAmount: 10000,
            currency: 'USD',
            installments: []
        );

        $violations = $this->validator->validate($request);

        $this->assertGreaterThan(0, $violations->count());
        $this->assertStringContainsString('invoiceId', (string) $violations->get(0)->getPropertyPath());
    }

    public function test_total_amount_must_be_positive(): void
    {
        $request = new CreatePaymentPlanRequest(
            invoiceId: 'inv-123',
            totalAmount: 0,
            currency: 'USD',
            installments: []
        );

        $violations = $this->validator->validate($request);

        $this->assertGreaterThan(0, $violations->count());
    }

    public function test_currency_must_be_three_characters(): void
    {
        $request = new CreatePaymentPlanRequest(
            invoiceId: 'inv-123',
            totalAmount: 10000,
            currency: 'US',
            installments: []
        );

        $violations = $this->validator->validate($request);

        $this->assertGreaterThan(0, $violations->count());
    }

    public function test_installments_are_validated(): void
    {
        $request = new CreatePaymentPlanRequest(
            invoiceId: 'inv-123',
            totalAmount: 10000,
            currency: 'USD',
            installments: [] // Empty installments violates min: 1 constraint
        );

        $violations = $this->validator->validate($request);

        $this->assertGreaterThan(0, $violations->count());
        $this->assertStringContainsString('installments', (string) $violations->get(0)->getPropertyPath());
    }

    public function test_invalid_installment_due_date_fails(): void
    {
        $request = new CreatePaymentPlanRequest(
            invoiceId: 'inv-123',
            totalAmount: 10000,
            currency: 'USD',
            installments: [
                new InstallmentRequest('invalid-date', 5000),
            ]
        );

        $violations = $this->validator->validate($request);

        $this->assertGreaterThan(0, $violations->count());
    }

    public function test_negative_installment_amount_fails(): void
    {
        $request = new CreatePaymentPlanRequest(
            invoiceId: 'inv-123',
            totalAmount: 10000,
            currency: 'USD',
            installments: [
                new InstallmentRequest('2024-01-15', -5000),
            ]
        );

        $violations = $this->validator->validate($request);

        $this->assertGreaterThan(0, $violations->count());
    }
}