<?php

namespace App\Core\Infrastructure\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Twig\Environment;

#[AsEventListener]
class MaintenanceListener
{
    public function __construct(
        private Environment $twig,
    )
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $onMaintenance = file_exists(dirname(__DIR__, 4) . '/.maintenance');
        $isCli = \PHP_SAPI === 'cli';

        if ($onMaintenance && !$isCli) {
            $response = new Response($this->twig->render('maintenance/index.html.twig'), Response::HTTP_SERVICE_UNAVAILABLE);
            $event->setResponse($response);
            $event->stopPropagation();
        }
    }
}