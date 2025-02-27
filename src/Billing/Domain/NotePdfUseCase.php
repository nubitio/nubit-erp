<?php

namespace App\Billing\Domain;

use App\Core\Domain\Exception\ServiceException;

readonly class NotePdfUseCase
{
    public function __construct(
        private NoteCreateUseCase $noteCreateUseCase,
        private NoteRepositoryInterface $noteRepository,
    ) {
    }

    /**
     * @throws ServiceException
     */
    public function __invoke(string $noteId): FileResponse
    {
        $note = $this->noteCreateUseCase->__invoke($noteId);
        $pdf = $this->noteRepository->pdf($note);

        return new FileResponse($pdf, $note->getName() . '.pdf');
    }
}
