<?php

declare(strict_types=1);

namespace App\Identity\Auth;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

#[AsEventListener(event: KernelEvents::CONTROLLER, priority: -128)]
final readonly class RefreshTransactionListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function __invoke(ControllerEvent $event): void
    {
        if (
            !$event->isMainRequest()
            || $event->getRequest()->attributes->get('_route') !== 'nubit_admin_auth_refresh'
        ) {
            return;
        }
        /** @var callable(Request): Response $controller */
        $controller = $event->getController();
        $event->setController(function (Request $request) use ($controller): Response {
            $connection = $this->entityManager->getConnection();
            $connection->beginTransaction();
            try {
                $response = $controller($request);
                if ($response->isSuccessful()) {
                    $connection->commit();
                } else {
                    $connection->rollBack();
                    $this->entityManager->clear();
                }
                return $response;
            } catch (Throwable $error) {
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
                $this->entityManager->clear();
                throw $error;
            }
        });
    }
}
