<?php

namespace App\Receipt\Domain;

use App\Billing\Domain\FileResponse;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\FileManager;
use App\Core\Infrastructure\Service\SettingsService;
use App\CreditNote\Domain\GetOneCreditNoteUseCase;
use App\Invoice\Domain\GetOneVoucherUseCase;
use League\Flysystem\FilesystemException;
use Psr\Cache\InvalidArgumentException;

readonly class ReceiptCdrUseCase
{
    public function __construct(
        private GetOneVoucherUseCase $getOneVoucherUseCase,
        private GetOneCreditNoteUseCase $getOneCreditNoteUseCase,
        private SettingsService $settings,
        private FileManager $fileManager
    ) {
    }

    /**
     * @throws ServiceException
     * @throws InvalidArgumentException
     * @throws FilesystemException
     */
    public function __invoke(ReceiptRequest $receiptRequest): FileResponse
    {
        return match ($receiptRequest->type) {
            ReceiptType::FACTURA, ReceiptType::BOLETA => $this->getCdrForInvoice($receiptRequest->id),
            ReceiptType::NOTA_CREDITO, ReceiptType::NOTA_DEBITO => $this->getCdrForNote($receiptRequest->id),
        };
    }

    /**
     * @throws InvalidArgumentException
     * @throws FilesystemException
     */
    public function getCdrForInvoice(string $id): FileResponse
    {
        $voucher = $this->getOneVoucherUseCase->__invoke($id);
        $companyId = $this->settings->get(Constants::COMPANY_ID);

        $docName = $voucher->voucher_series.'-'.$voucher->voucher_number;
        $docFullName = $companyId.'-'.$voucher->voucher_type.'-'.$docName;

        return new FileResponse(
            $this->fileManager->read($companyId.'/R-'.$docFullName.'.zip'),
            'R-'.$docFullName.'.zip'
        );
    }

    /**
     * @throws InvalidArgumentException
     * @throws ServiceException
     * @throws FilesystemException
     */
    public function getCdrForNote(string $id): FileResponse
    {
        $voucher = $this->getOneCreditNoteUseCase->__invoke($id);
        $companyId = $this->settings->get(Constants::COMPANY_ID);

        $docName = $voucher->serie.'-'.$voucher->correlativo;
        $docFullName = $companyId.'-'.$voucher->tipo_id.'-'.$docName;

        return new FileResponse(
            $this->fileManager->read($companyId.'/R-'.$docFullName.'.zip'),
            'R-'.$docFullName.'.zip'
        );
    }
}
