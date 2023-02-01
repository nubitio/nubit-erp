<?php

namespace App\Core\Infrastructure\EventListener;

use ApiPlatform\State\Pagination\PaginatorInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AsEventListener]
class ApiResponseListener
{
    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        if ($data instanceof PaginatorInterface) {
            $response = $event->getResponse();
            $response->headers->add([
                'X-Total-Count' => $data->getTotalItems()
            ]);
        }
    }
}