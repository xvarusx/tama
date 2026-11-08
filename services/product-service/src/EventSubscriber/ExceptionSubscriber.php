<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class ExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 2],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable(); //

        // Default values for unexpected errors
        // $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        // $responseData = [
        //     'error' => 'Internal Server Error',
        //     'message' => 'An unexpected error occurred.',
        // ];
        //
        // // 1. Handle Symfony's built-in HTTP Exceptions (404 Not Found, 403 Forbidden, etc.)
        // if ($exception instanceof HttpExceptionInterface) { //
        //     $statusCode = $exception->getStatusCode(); //
        //     $responseData['error'] = $this->getErrorPhrase($statusCode);
        //     $responseData['message'] = $exception->getMessage();
        // }
        //
        // // 2. Handle API Validation Errors (e.g., from serializer/validator)
        // if ($exception->getPrevious() instanceof ValidationFailedException) {
        //     /** @var ValidationFailedException $validationException */
        //     $validationException = $exception->getPrevious();
        //     $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY;
        //
        //     $errors = [];
        //     foreach ($validationException->getViolations() as $violation) {
        //         $errors[$violation->getPropertyPath()] = $violation->getMessage();
        //     }
        //
        //     $responseData = [
        //         'error' => 'Validation Failed',
        //         'message' => 'The submitted data is invalid.',
        //         'violations' => $errors,
        //     ];
        // }
        //
        // // Create the JSON response and set it on the event
        // $response = new JsonResponse($responseData, $statusCode); // [0.6.4, Response]
        // $event->setResponse($response); //
    }

    // private function getErrorPhrase(int $statusCode): string
    // {
    //     return Response::$statusTexts[$statusCode] ?? 'Unknown Error';
    // }
}
