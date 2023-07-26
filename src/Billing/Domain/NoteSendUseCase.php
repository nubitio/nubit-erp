<?php

namespace App\Billing\Domain;

use App\Billing\Model\Response\DocumentResponse;
use App\Core\Domain\Exception\ServiceException;

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
    public function __invoke(string $noteId): DocumentResponse
    {
        $note = $this->noteCreateUseCase->__invoke($noteId);
        $response = $this->noteRepository->send($note);
        $response->name = $note->getName();

        return $response;
    }
}
