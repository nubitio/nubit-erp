<?php

declare(strict_types=1);

namespace App\Billing\Domain;

use App\Billing\Model\Response\DocumentResponse;
use App\Core\Exception\ServiceException;

readonly class NoteSendUseCase
{
    public function __construct(
        private NoteCreateUseCase $noteCreateUseCase,
        private NoteRepositoryInterface $noteRepository,
    ) {
    }

    /**
     * @throws ServiceException
     */
    public function __invoke(string $taxDocumentId): DocumentResponse
    {
        $note = $this->noteCreateUseCase->__invoke($taxDocumentId);
        $response = $this->noteRepository->send($note);
        $response->name = $note->getName();

        return $response;
    }
}
