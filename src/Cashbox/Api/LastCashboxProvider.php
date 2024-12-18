<?php

namespace App\Cashbox\Api;

use ApiPlatform\Metadata\Operation;
use App\Cashbox\Domain\Cashbox;
use App\Cashbox\Domain\CashboxGetLastUseCase;
use App\Core\Domain\Api\ApiProviderInterface;

/**
 * @implements ApiProviderInterface<Cashbox>
 */
readonly class LastCashboxProvider implements ApiProviderInterface
{
    public function __construct(
        private CashboxGetLastUseCase $getLastUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $branchId = $uriVariables['branch_id'];

        return $this->getLastUseCase->__invoke($branchId);
    }
}
