<?php

namespace App\Tests\Unit\EventListener;

use App\EventListener\ExceptionListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ExceptionListenerTest extends TestCase
{
    public function testNotFoundHttpExceptionReturns404Json(): void
    {
        $response = $this->runListener(new NotFoundHttpException('Client not found'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type') ?? 'application/json');
        $body = json_decode($response->getContent(), true);
        self::assertSame(['error' => 'Client not found'], $body);
    }

    public function testMethodNotAllowedHttpExceptionReturns405(): void
    {
        $response = $this->runListener(new MethodNotAllowedHttpException(['GET']));

        self::assertSame(405, $response->getStatusCode());
    }

    public function testHttpExceptionInterfaceKeepsItsStatusCode(): void
    {
        $response = $this->runListener(new ServiceUnavailableHttpException(null, 'Maintenance'));

        self::assertSame(503, $response->getStatusCode());
    }

    public function testNonHttpExceptionReturns500(): void
    {
        $response = $this->runListener(new \RuntimeException('boom'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(['error' => 'boom'], json_decode($response->getContent(), true));
    }

    private function runListener(\Throwable $exception): \Symfony\Component\HttpFoundation\Response
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ExceptionEvent::class, [new ExceptionListener(), 'onExceptionEvent']);

        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/clients'),
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $dispatcher->dispatch($event, ExceptionEvent::class);

        self::assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $event->getResponse());

        return $event->getResponse();
    }
}
