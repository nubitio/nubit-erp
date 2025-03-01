<?php

namespace App\CreditNote\Domain;

use App\Core\Domain\Exception\ServiceException;

class GetOneCreditNoteUseCase
{
    public function __construct(private readonly CreditNoteRepositoryInterface $repository)
    {
    }

    /**
     * @throws ServiceException
     */
    public function __invoke(string $id): CreditNote
    {
        $creditNote = $this->repository->getOne($id);

        if (!$creditNote instanceof \App\CreditNote\Domain\CreditNote) {
            throw new ServiceException('No se encontró la nota de crédito.');
        }

        return $creditNote;
    }
}
