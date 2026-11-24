<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class ApiExceptionSubscriber
{
    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 2)]
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        $responseData = [
            'error' => 'internal_error',
            'message' => 'An unexpected error occurred.',
        ];

        // Handle Symfony's built-in HTTP Exceptions (404 Not Found, 403 Forbidden, etc.)
        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $responseData = [
                'error' => 'error',
                'message' => $exception->getMessage(),
            ];
        }

        // Handle API Validation Errors
        if ($exception->getPrevious() instanceof ValidationFailedException) {
            /** @var ValidationFailedException $validationException */
            $validationException = $exception->getPrevious();
            $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY;

            $errors = [];
            foreach ($validationException->getViolations() as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            $responseData = [
                'status' => 'error',
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'The submitted data is invalid.',
                    'violations' => $errors,
                ],
            ];
        }

        $response = new JsonResponse($responseData, $statusCode);
        $event->setResponse($response);
    }
}