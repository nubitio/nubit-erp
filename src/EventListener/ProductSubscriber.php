<?php

namespace App\EventListener;

use App\Domain\ProductLocal\CreateProductLocal;
use App\Domain\ProductLocal\ProductLocal;
use App\Event\ProductCreatedEvent;
use App\Event\ProductDeletedEvent;
use App\Event\ProductUpdatedEvent;
use App\Service\SessionService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductSubscriber implements EventSubscriberInterface
{
    private SessionService $sessionService;
    private CreateProductLocal $createProductLocal;

    public function __construct(SessionService $sessionService, CreateProductLocal $createProductLocal)
    {
        $this->sessionService = $sessionService;
        $this->createProductLocal = $createProductLocal;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductCreatedEvent::NAME => 'onProductCreated',
            ProductUpdatedEvent::NAME => 'onProductUpdated',
            ProductDeletedEvent::NAME => 'onProductDeleted',
        ];
    }

    public function onProductCreated(ProductCreatedEvent $event)
    {
        $productLocal = new ProductLocal();
        $productLocal->setLocalId($this->sessionService->get('local_id'));
        $productLocal->setProductId($event->getProduct()->getId());
        $productLocal->setPrice($event->getProduct()->getPrice());
        $productLocal->setActive(true);
        $this->createProductLocal->execute($productLocal);
    }

    public function onProductUpdated(ProductUpdatedEvent $event)
    {
        dd($event->getProduct());
    }

    public function onProductDeleted(ProductDeletedEvent $event)
    {
        dd($event->getProduct());
    }
}