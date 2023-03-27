<?php

namespace App\Core\Infrastructure\EventListener;

use App\Core\Domain\Exception\ServiceException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

#[AsEventListener]
class ExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof ServiceException) {
            return;
        }

        $response = new JsonResponse([
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
        ], Response::HTTP_BAD_REQUEST);
        $event->setResponse($response);
    }
}