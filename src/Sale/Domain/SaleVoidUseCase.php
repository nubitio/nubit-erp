<?php

namespace App\Sale\Domain;

use App\Billing\Domain\VoidedCreateUseCase;
use App\Invoice\Domain\InvoiceUpdateUseCase;

class SaleVoidUseCase
{
    private SaleRepositoryInterface $repository;
    private VoidedCreateUseCase $voidedCreateUseCase;
    private InvoiceUpdateUseCase $invoiceUpdateUseCase;

    public function __construct(
        SaleRepositoryInterface $repository,
        VoidedCreateUseCase     $voidedCreateUseCase,
        InvoiceUpdateUseCase    $invoiceUpdateUseCase
    )
    {
        $this->repository = $repository;
        $this->voidedCreateUseCase = $voidedCreateUseCase;
        $this->invoiceUpdateUseCase = $invoiceUpdateUseCase;
    }

    /**
     * @throws \Exception
     */
    public function __invoke(string $saleId, string $reason): void
    {
        $sale = $this->repository->getOneById($saleId);

        if ($sale['header']['doc_type'] === DocType::FACTURA->value) {
            $response = $this->voidedCreateUseCase->__invoke($saleId, $reason);
            if ($response->sunatResponse->success) {
                $xmlFile = base64_decode($response->xml);
                file_put_contents($_ENV['EFACT_DIR'] . $response->name . '.xml', $xmlFile);
            } else {
                throw new \Exception($response->sunatResponse->error->message);
            }
        } else {
            throw new \Exception('Unsupported document type');
        }
    }
}