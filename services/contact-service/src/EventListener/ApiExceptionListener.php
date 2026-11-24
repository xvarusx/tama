<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exceptions\ApiException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

#[AsEventListener(event: ExceptionEvent::class, priority: 10)]
final class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if (!$throwable instanceof ApiException) {
            return;
        }

        $response = new JsonResponse(
            [
                'status' => 'error',
                'error' => [
                    'code' => $throwable->getErrorCode(),
                    'message' => $throwable->getMessage(),
                ],
            ],
            $throwable->getHttpStatus(),
        );

        $event->setResponse($response);
    }
}
