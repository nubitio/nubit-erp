<?php

namespace App\Core\EventListener;

use App\Cashbox\Domain\CashboxUpdateValuesUseCase;
use App\Sale\Infrastructure\SaleEntity;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\HttpFoundation\RequestStack;

class SaleCreatedListener
{
    public function __construct(
        private readonly RequestStack               $requestStack,
        private readonly CashboxUpdateValuesUseCase $cashboxUpdateValues,
    )
    {
    }

    public function postPersist(SaleEntity $entity, LifecycleEventArgs $event): void
    {
        $session = $this->requestStack->getSession();
        $localId = $session->get('local_id');
        $userId = $session->get('usuario_id');

        $this->cashboxUpdateValues->__invoke($localId, $userId);
    }
}