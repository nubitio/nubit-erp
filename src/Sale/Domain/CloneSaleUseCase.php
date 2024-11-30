<?php

namespace App\Sale\Domain;

readonly class CloneSaleUseCase
{
    public function __construct(
        private GetOneSaleUseCase $getOneSaleUseCase,
        private CreateSaleUseCase $createSaleUseCase,
    ) {
    }

    public function __invoke(string $saleId): Sale
    {
        $sale = $this->getOneSaleUseCase->__invoke($saleId);
        $sale->movimiento_id = null;
        $sale->fecha = new \DateTime();
        $sale->date_created = new \DateTime();
        $sale->serie = null;
        $sale->numero = null;
        $sale->has_invoice = false;
        $sale->invoice = null;
        $sale->estado = 1;

        foreach ($sale->details as $detail) {
            $detail->id = null;
            $detail->movimiento = null;
        }

        return $this->createSaleUseCase->__invoke($sale);
    }
}
