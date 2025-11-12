<?php

namespace App\Core\EventListener;

use App\Core\Exception\ServiceException;
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

        $isDev = 'dev' === $_ENV['APP_ENV'];

        $data = [];
        $data['message'] = $exception->getMessage();
        $data['code'] = $exception->getCode();

        if ($isDev) {
            $data['trace'] = $exception->getTrace();
        }

        $response = new JsonResponse($data, Response::HTTP_BAD_REQUEST);
        $event->setResponse($response);
    }
}
