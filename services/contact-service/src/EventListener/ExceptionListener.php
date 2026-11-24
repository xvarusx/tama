<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ExceptionListener
{
    #[AsEventListener]
    public function onExceptionEvent(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $status = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        $message = $exception instanceof HttpExceptionInterface
            ? $exception->getMessage()
            : 'An unexpected error occurred.';

        // Include exception class and trace in debug mode
        $debug = $_ENV['APP_DEBUG'] ?? false;
        $responseData = ['error' => $message];
        if ($debug) {
            $responseData['exception'] = get_class($exception);
            $responseData['trace'] = $exception->getTraceAsString();
        }

        $event->setResponse(new JsonResponse($responseData, $status));
    }
}
