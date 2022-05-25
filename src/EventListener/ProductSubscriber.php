<?php

namespace App\EventListener;

use App\Domain\ProductLocal\CreateProductLocal;
use App\Domain\ProductLocal\DeleteProductLocal;
use App\Domain\ProductLocal\ProductLocal;
use App\Domain\ProductLocal\UpdateProductLocal;
use App\Event\ProductCreatedEvent;
use App\Event\ProductDeletedEvent;
use App\Event\ProductUpdatedEvent;
use App\Service\SessionService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductSubscriber implements EventSubscriberInterface
{
    private SessionService $sessionService;
    private CreateProductLocal $createProductLocal;
    private UpdateProductLocal $updateProductLocal;
    private DeleteProductLocal $deleteProductLocal;

    public function __construct(
        SessionService     $sessionService,
        CreateProductLocal $createProductLocal,
        UpdateProductLocal $updateProductLocal,
        DeleteProductLocal $deleteProductLocal
    )
    {
        $this->sessionService = $sessionService;
        $this->createProductLocal = $createProductLocal;
        $this->updateProductLocal = $updateProductLocal;
        $this->deleteProductLocal = $deleteProductLocal;
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
        $productLocal = new ProductLocal();
        $productLocal->setLocalId($this->sessionService->get('local_id'));
        $productLocal->setProductId($event->getProduct()->getId());
        $productLocal->setPrice($event->getProduct()->getPrice());
        $productLocal->setActive($event->getProduct()->isActive());
        $this->updateProductLocal->execute($productLocal);
    }

    public function onProductDeleted(ProductDeletedEvent $event)
    {
        $imagePath = 'uploads/images/' . $event->getProduct()->getImage();

        if (file_exists($imagePath)) {
            unlink($imagePath);
        }

        $this->deleteProductLocal->execute($event->getProduct()->getId(), $this->sessionService->get('local_id'));
    }
}