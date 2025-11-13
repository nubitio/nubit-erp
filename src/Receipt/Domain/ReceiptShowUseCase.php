<?php

namespace App\Receipt\Domain;

use App\Core\Exception\ServiceException;
use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Repository\CreditNoteRepository;
use App\Repository\InvoiceRepository;
use Doctrine\DBAL\Exception;

readonly class ReceiptShowUseCase
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private CreditNoteRepository $creditNoteRepository,
        private SettingsService $settings,
    ) {
    }

    /**
     * @throws ServiceException
     * @throws Exception
     */
    public function __invoke(ReceiptRequest $request): Receipt
    {
        return match ($request->type) {
            ReceiptType::FACTURA, ReceiptType::BOLETA => $this->makeReceiptForInvoice($request->id),
            ReceiptType::NOTA_CREDITO, ReceiptType::NOTA_DEBITO => $this->makeReceiptForNote($request->id),
        };
    }

    /**
     * @throws Exception
     */
    private function makeReceiptForInvoice(string $id): Receipt
    {
        $voucher = $this->invoiceRepository->getVoucher($id);
        $companyName = $this->settings->get(Constants::COMPANY_NAME);

        return new Receipt(
            company: strval($companyName),
            type: ($voucher->voucher_type) === '01' ? 'FACTURA' : 'BOLETA',
            series: strval($voucher->voucher_series),
            number: strval($voucher->voucher_number),
            date: strval($voucher->voucher_date),
            total: floatval($voucher->voucher_sale_amount)
        );
    }

    /**
     * @throws ServiceException
     * @throws Exception
     */
    private function makeReceiptForNote(string $id): Receipt
    {
        $note = $this->creditNoteRepository->find($id);
        $companyName = $this->settings->get(Constants::COMPANY_NAME);

        return new Receipt(
            company: strval($companyName),
            type: ($note->tipo_id) === '07' ? 'NOTA DE CRÉDITO' : 'NOTA DE DÉBITO',
            series: strval($note->getSerie()),
            number: strval($note->getCorrelativo()),
            date: strval($note->getFecha()?->format('Y-m-d')),
            total: floatval($note->getTotal())
        );
    }
}
