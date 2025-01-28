<?php

namespace App\CashboxNew\Infrastructure;

use App\CashboxNew\Domain\CashboxUpdateValuesUseCase;
use App\Core\Application\Session\Security;
use App\Sale\Domain\SaleCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(
    event: SaleCreatedEvent::NAME,
    method: '__invoke'
)]
final readonly class SaleCreatedListener
{
    public function __construct(
        private Security $security,
        private CashboxUpdateValuesUseCase $cashboxUpdateValuesUseCase,
    ) {
    }

    public function __invoke(SaleCreatedEvent $event): void
    {
        if ('cli' === php_sapi_name()) {
            return;
        }
        $branchId = $this->security->connectedUser()?->branchId;
        $userId = $this->security->connectedUser()?->userId;

        if (!$branchId || !$userId) {
            return;
        }

        $this->cashboxUpdateValuesUseCase->__invoke($branchId, $userId);
    }
}
