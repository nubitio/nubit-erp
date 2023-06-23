<?php

namespace App\Receipt\Domain;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\TenantAwareSettingsService;
use App\CreditNote\Domain\GetOneCreditNoteUseCase;
use App\Invoice\Domain\GetOneVoucherUseCase;
use Psr\Cache\InvalidArgumentException;

readonly class ReceiptShowUseCase
{
    public function __construct(
        private GetOneVoucherUseCase $getOneVoucherUseCase,
        private GetOneCreditNoteUseCase $getOneNoteUseCase,
        private TenantAwareSettingsService $settings
    ) {
    }

    /**
     * @throws ServiceException
     * @throws InvalidArgumentException
     */
    public function __invoke(ReceiptRequest $request): Receipt
    {
        return match ($request->type) {
            ReceiptType::FACTURA, ReceiptType::BOLETA => $this->makeReceiptForInvoice($request->id),
            ReceiptType::NOTA_CREDITO, ReceiptType::NOTA_DEBITO => $this->makeReceiptForNote($request->id),
        };
    }

    /**
     * @throws InvalidArgumentException
     */
    private function makeReceiptForInvoice(string $id): Receipt
    {
        $voucher = $this->getOneVoucherUseCase->__invoke($id);
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
     * @throws InvalidArgumentException
     */
    private function makeReceiptForNote(string $id): Receipt
    {
        $note = $this->getOneNoteUseCase->__invoke($id);
        $companyName = $this->settings->get(Constants::COMPANY_NAME);

        return new Receipt(
            company: strval($companyName),
            type: ($note->tipo_id) === '07' ? 'NOTA DE CRÉDITO' : 'NOTA DE DÉBITO',
            series: strval($note->serie),
            number: strval($note->correlativo),
            date: strval($note->fecha?->format('Y-m-d')),
            total: floatval($note->total)
        );
    }
}
