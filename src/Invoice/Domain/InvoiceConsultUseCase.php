<?php

namespace App\Invoice\Domain;

use App\Billing\Consult;
use App\Sale\Domain\SaleRepositoryInterface;
use App\Service\SettingsService;

class InvoiceConsultUseCase
{
    public function __construct(
        private readonly SaleRepositoryInterface    $saleRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly SettingsService            $settingsService,
    )
    {
    }

    public function __invoke(string $saleId): array
    {
        $sale = $this->saleRepository->getOneById($saleId);
        $invoice = $this->invoiceRepository->getOne($saleId);
        $consult = new Consult();

        $ruc = $this->settingsService->get('invoice_ruc');
        $user = $this->settingsService->get('invoice_user');
        $pass = $this->settingsService->get('invoice_password');

        $type = $sale['header']['doc_type'];
        $series = $sale['header']['doc_series'];
        $number = $sale['header']['doc_number'];

        $result = $consult->consult($ruc, $user, $pass, $type, $series, $number);

        if ($result['success']) {
            $invoice->envio_sunat = $result['cdr']->getCode();
            $invoice->mensaje_sunat = $result['cdr']->getDescription();
            $invoice->updated_at = new \DateTime();
            $this->invoiceRepository->update($invoice);
        }

        return $result;
    }
}