<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\Invoice;
use App\EventListener\ApiExceptionListener;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

final class ApiExceptionListenerTest extends TestCase
{
    public function testOptimisticLockBecomesA409ErrorEnvelope(): void
    {
        $event = $this->event(OptimisticLockException::lockFailed(Invoice::class));

        new ApiExceptionListener()->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(409, $response->getStatusCode());
        $body = json_decode((string)$response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('error', $body['status']);
        self::assertSame('CONCURRENT_MODIFICATION', $body['error']['code']);
    }

    public function testUnrelatedExceptionsAreLeftForTheFramework(): void
    {
        $event = $this->event(new RuntimeException('boom'));

        new ApiExceptionListener()->onKernelException($event);

        self::assertNull($event->getResponse());
    }

    private function event(Throwable $exception): ExceptionEvent
    {
        return new ExceptionEvent(
            self::createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }
}
