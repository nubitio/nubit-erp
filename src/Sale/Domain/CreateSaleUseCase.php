<?php

namespace App\Sale\Domain;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class CreateSaleUseCase
{
    public function __construct(
        private SaleRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function __invoke(Sale $sale): Sale
    {
        $createdSale = $this->repository->insert($sale);

        $saleCreatedEvent = new SaleCreatedEvent($createdSale);

        $this->eventDispatcher->dispatch($saleCreatedEvent, SaleCreatedEvent::NAME);

        return $createdSale;
    }
}
