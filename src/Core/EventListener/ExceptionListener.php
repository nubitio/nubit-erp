<?php

namespace App\Core\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class ExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $response = new JsonResponse([
            'message' => $exception->getMessage(),
            'code'    => $exception->getCode(),
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
        $event->setResponse($response);
    }
}