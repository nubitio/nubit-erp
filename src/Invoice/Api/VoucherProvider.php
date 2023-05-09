<?php

namespace App\Invoice\Api;

use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Invoice\Domain\GetOneVoucherUseCase;
use App\Invoice\Domain\Voucher;

/**
 * @implements ApiProviderInterface<Voucher>
 */
readonly class VoucherProvider implements ApiProviderInterface
{
    public function __construct(
        private GetOneVoucherUseCase $getOneUseCase,
    )
    {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return $this->getOneUseCase->__invoke(strval($uriVariables['sale_id']));
    }
}