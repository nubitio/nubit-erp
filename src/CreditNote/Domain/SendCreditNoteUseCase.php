<?php

namespace App\CreditNote\Domain;

use App\Billing\Domain\NoteSendUseCase;
use App\Billing\Model\Response\DocumentResponse;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\FileManager;
use App\Core\Infrastructure\Service\SettingsService;
use Doctrine\DBAL\Exception;
use League\Flysystem\FilesystemException;

readonly class SendCreditNoteUseCase
{
    public function __construct(
        private GetOneCreditNoteUseCase $getOneCreditNoteUseCase,
        private NoteSendUseCase $noteSendUseCase,
        private CreditNoteRepositoryInterface $creditNoteRepository,
        private FileManager $fileManager,
        private SettingsService $settings
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws ServiceException
     * @throws Exception
     */
    public function __invoke(string $noteId): DocumentResponse
    {
        $note = $this->getOneCreditNoteUseCase->__invoke($noteId);

        if (null !== $note->envio_sunat) {
            throw new ServiceException('La nota de crédito ya fue enviada.');
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
        }

        return $response;
    }
}
