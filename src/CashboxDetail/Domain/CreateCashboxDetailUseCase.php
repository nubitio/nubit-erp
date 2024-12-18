<?php

namespace App\CashboxDetail\Domain;

use App\CashboxDetail\Domain\Event\CashboxOperationEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final readonly class CreateCashboxDetailUseCase
{
    public function __construct(
        private CashboxDetailRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(CashboxDetail $cashboxDetail): CashboxDetail
    {
        $cashboxDetail = $this->repository->insert($cashboxDetail);

        $this->eventDispatcher->dispatch(new CashboxOperationEvent($cashboxDetail));

        return $cashboxDetail;
    }
}
