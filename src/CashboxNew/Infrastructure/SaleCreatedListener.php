<?php

namespace App\CashboxNew\Infrastructure;

use App\CashboxNew\Domain\CashboxUpdateValuesUseCase;
use App\Sale\Domain\SaleCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsEventListener(
    event: SaleCreatedEvent::NAME,
    method: '__invoke'
)]
final readonly class SaleCreatedListener
{
    public function __construct(
        private RequestStack $requestStack,
        private CashboxUpdateValuesUseCase $cashboxUpdateValuesUseCase
    ) {
    }

    public function __invoke(SaleCreatedEvent $event): void
    {
        if ('cli' === php_sapi_name()) {
            return;
        }
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
