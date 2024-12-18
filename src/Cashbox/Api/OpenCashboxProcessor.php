<?php

namespace App\Cashbox\Api;

use ApiPlatform\Metadata\Operation;
use App\Branch\Domain\Branch;
use App\Cashbox\Domain\Cashbox;
use App\Cashbox\Domain\CashboxCreateUseCase;
use App\CashboxDetail\Domain\CashboxDetail;
use App\CashboxDetail\Domain\CreateCashboxDetailUseCase;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\User\Domain\User;

/**
 * @implements ApiProcessorInterface<OpenCashboxDto, Cashbox>
 */
readonly class OpenCashboxProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CashboxCreateUseCase $createUseCase,
        public CreateCashboxDetailUseCase $createCashboxDetailUseCase,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Cashbox
    {
        $cashBoxDetail = new CashboxDetail(
            uid: 1,
            tipo_id: 1,
            descripcion: 'FONDO DE CAJA',
            monto: $data->amount,
        );

        $cashBox = new Cashbox(
            local: new Branch($data->branch_id),
            apertura: new \DateTime(),
            usuario: new User($data->user_id),
            estado: 1
        );

        $cashbox = $this->createUseCase->__invoke($cashBox);
        $cashBoxDetail->caja_id = $cashbox->caja_id;
        $this->createCashboxDetailUseCase->__invoke($cashBoxDetail);

        return $cashbox;
    }
}
