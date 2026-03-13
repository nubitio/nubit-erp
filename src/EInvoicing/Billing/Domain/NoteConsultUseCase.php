<?php

declare(strict_types=1);

namespace App\EInvoicing\Billing\Domain;

use Greenter\Model\Response\StatusCdrResult;

readonly class NoteConsultUseCase
{
    public function __construct(
        private NoteCreateUseCase $noteCreateUseCase,
        private NoteRepositoryInterface $noteRepository,
    ) {
    }

    public function __invoke(string $taxDocumentId): StatusCdrResult
    {
        $note = $this->noteCreateUseCase->__invoke($taxDocumentId);

        return $this->noteRepository->getStatus($note);
    }
}
