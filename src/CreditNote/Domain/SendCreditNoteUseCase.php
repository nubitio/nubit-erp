<?php

namespace App\CreditNote\Domain;

use App\Billing\Domain\NoteSendUseCase;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\FileManager;
use App\Core\Infrastructure\Service\TenantAwareSettingsService;
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
        private TenantAwareSettingsService $settings
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
        if ($response->sunatResponse->success) {
            $note->envio_sunat = $response->sunatResponse->cdrResponse->code;
            $note->mensaje_sunat = $response->sunatResponse->cdrResponse->description;
            $cdr = base64_decode($response->sunatResponse->cdrZip);
            $this->fileManager->write($this->settings->get(Constants::COMPANY_ID).'/R-'.$response->name.'.zip', $cdr);
            $this->creditNoteRepository->update($note);
        } else {
            $note->envio_sunat = $response->sunatResponse->error->code;
            $note->mensaje_sunat = $response->sunatResponse->error->message;
            $this->creditNoteRepository->update($note);
            throw new ServiceException($response->sunatResponse->error->message);
        }

        return $this->getOneVoucherUseCase->__invoke($saleId);
    }
}
