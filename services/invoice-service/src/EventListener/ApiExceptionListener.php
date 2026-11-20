<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exceptions\ApiException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Expected business errors become the API's own error envelope, not a 500. */
#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class ApiExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof OptimisticLockException) {
            $event->setResponse(new JsonResponse([
                'status' => 'error',
                'error' => [
                    'code' => 'CONCURRENT_MODIFICATION',
                    'message' => 'The invoice was modified concurrently; reload it and retry.',
                ],
            ], 409));

            return;
        }

        if (!$exception instanceof ApiException) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'status' => 'error',
            'error' => ['code' => $exception->getErrorCode(), 'message' => $exception->getMessage()],
        ], $exception->getHttpStatus()));
    }
}
