<?php

namespace App\CreditNote\Domain;

class UpdateCreditNoteUseCase
{
    private CreditNoteRepositoryInterface $repository;

    public function __construct(CreditNoteRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(CreditNote $creditNote): CreditNote
    {
        return $this->repository->update($creditNote);
    }
}
