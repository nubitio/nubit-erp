<?php

namespace App\Cashbox\Infrastructure;

use App\Cashbox\Domain\CashboxUpdateValuesUseCase;
use App\Sale\Infrastructure\SaleEntity;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsEntityListener(Events::postPersist, entity: SaleEntity::class)]
#[AsEntityListener(Events::postUpdate, entity: SaleEntity::class)]
readonly class SaleCreatedListener
{
    public function __construct(
        private RequestStack $requestStack,
        private CashboxUpdateValuesUseCase $cashboxUpdateValuesUseCase
    ) {
    }

    public function __invoke(SaleEntity $entity): void
    {
        $session = $this->requestStack->getSession();
        /** @var ?string $branchId */
        $branchId = $session->get('branch_id');
        /** @var ?int $userId */
        $userId = $session->get('user_id');

        if (!$branchId || !$userId) {
            return;
        }

        $this->cashboxUpdateValuesUseCase->__invoke($branchId, $userId);
    }
}
