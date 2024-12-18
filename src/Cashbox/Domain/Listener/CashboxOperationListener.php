<?php

namespace App\Cashbox\Domain\Listener;

use App\Cashbox\Domain\GetCashboxBalanceUseCase;
use App\Cashbox\Domain\GetOneCashboxUseCase;
use App\Cashbox\Domain\UpdateCashboxUseCase;
use App\CashboxDetail\Domain\Event\CashboxOperationEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
readonly class CashboxOperationListener
{
    public function __construct(
        private GetOneCashboxUseCase $getOneCashboxUseCase,
        private GetCashboxBalanceUseCase $getCashboxBalanceUseCase,
        private UpdateCashboxUseCase $updateCashboxUseCase,
    ) {
    }

    public function __invoke(CashboxOperationEvent $event): void
    {
        $cashboxDetail = $event->cashboxDetail;
        $cashbox = $this->getOneCashboxUseCase->__invoke($cashboxDetail->caja_id);
        $balance = $this->getCashboxBalanceUseCase->__invoke($cashbox->caja_id);
        $cashbox->balance = $balance;
        $this->updateCashboxUseCase->__invoke($cashbox);
    }
}
