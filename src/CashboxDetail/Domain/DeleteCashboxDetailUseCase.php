<?php

namespace App\CashboxDetail\Domain;

use App\CashboxDetail\Domain\Event\CashboxOperationEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final readonly class DeleteCashboxDetailUseCase
{
    public function __construct(
        private CashboxDetailRepositoryInterface $repository,
        private GetOneCashboxDetailUseCase $getOneCashboxDetailUseCase,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(int $id): void
    {
        $cashboxDetail = $this->getOneCashboxDetailUseCase->__invoke($id);

        $this->repository->delete($id);

        $this->eventDispatcher->dispatch(new CashboxOperationEvent($cashboxDetail));
    }
}
