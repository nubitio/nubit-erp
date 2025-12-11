<?php

declare(strict_types=1);

namespace App\Billing\Domain;

use App\Core\Exception\ServiceException;

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
