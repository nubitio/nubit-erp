<?php

namespace App\CreditNote\Domain;

use App\Billing\Domain\NoteSendUseCase;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\FileManager;
use App\Core\Infrastructure\Service\SettingsService;
use App\Invoice\Domain\GetOneVoucherUseCase;
use App\Invoice\Domain\Voucher;
use League\Flysystem\FilesystemException;
use Psr\Cache\InvalidArgumentException;

readonly class SendCreditNoteUseCase
{
    public function __construct(
        private GetOneCreditNoteUseCase $getOneCreditNoteUseCase,
        private GetOneVoucherUseCase $getOneVoucherUseCase,
        private NoteSendUseCase $noteSendUseCase,
        private CreditNoteRepositoryInterface $creditNoteRepository,
        private FileManager $fileManager,
        private SettingsService $settings
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws ServiceException|InvalidArgumentException
     */
    public function __invoke(string $noteId): ?Voucher
    {
        $note = $this->getOneCreditNoteUseCase->__invoke($noteId);
        $saleId = $note->sale?->movimiento_id;

        if (null === $saleId) {
            throw new ServiceException('$saleId no puede ser nulo.');
        }

        if (null !== $note->envio_sunat) {
            return $this->getOneVoucherUseCase->__invoke($saleId);
        }
        $response = $this->noteSendUseCase->__invoke($noteId);
        $note->digest = $response->hash;
        $this->fileManager->write($this->settings->get(Constants::COMPANY_ID).'/'.$response->name.'.xml', $response->xml);
        if ($response->sunatResponse->isSuccess()) {
            $note->envio_sunat = $response->sunatResponse->getCdrResponse()?->getCode();
            $note->mensaje_sunat = $response->sunatResponse->getCdrResponse()?->getDescription();
            $cdr = base64_decode($response->sunatResponse->getCdrZip() ?? '');
            $this->fileManager->write($this->settings->get(Constants::COMPANY_ID).'/R-'.$response->name.'.zip', $cdr);
            $this->creditNoteRepository->update($note);
        } else {
            $note->envio_sunat = $response->sunatResponse->getError()?->getCode();
            $note->mensaje_sunat = $response->sunatResponse->getError()?->getMessage();
            $this->creditNoteRepository->update($note);
            throw new ServiceException($response->sunatResponse->getError()?->getMessage() ?? '');
        }

        return $this->getOneVoucherUseCase->__invoke($saleId);
    }
}
