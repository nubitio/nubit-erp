<?php

namespace App\CreditNote\Domain;

use App\Core\Domain\Exception\ServiceException;

class GetOneCreditNoteUseCase
{
    private CreditNoteRepositoryInterface $repository;

    public function __construct(CreditNoteRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @throws ServiceException
     */
    public function __invoke(string $id): CreditNote
    {
        $creditNote = $this->repository->getOne($id);

        if (!$creditNote) {
            throw new ServiceException('No se encontró la nota de crédito.');
        }

        return $creditNote;
    }
}
