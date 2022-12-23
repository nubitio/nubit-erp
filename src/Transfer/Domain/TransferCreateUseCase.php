<?php

namespace App\Transfer\Domain;

use App\Inbound\Domain\Inbound;
use App\Inbound\Domain\InboundCreateUseCase;
use App\Outbound\Domain\Outbound;
use App\Outbound\Domain\OutboundCreateUseCase;
use Symfony\Component\Uid\Uuid;

class TransferCreateUseCase
{
    public function __construct(
        private readonly TransferRepositoryInterface $repository,
        private readonly OutboundCreateUseCase       $outboundCreateUseCase,
        private readonly InboundCreateUseCase        $inboundCreateUseCase,
    )
    {
    }

    public function __invoke(Transfer $transfer): void
    {
        $outbound = new Outbound();
        $outbound->movimiento_id = Uuid::v4();
        $outbound->fecha = $transfer->fecha;
        $outbound->almacen_id = $transfer->almacen_origen;
        $outbound->items = $transfer->items;
        $this->outboundCreateUseCase->__invoke($outbound);

        $inbound = new Inbound();
        $inbound->movimiento_id = Uuid::v4();
        $inbound->fecha = $transfer->fecha;
        $inbound->almacen_id = $transfer->almacen_destino;
        $inbound->items = $transfer->items;
        $this->inboundCreateUseCase->__invoke($inbound);

        $this->repository->insert($transfer);
    }
}