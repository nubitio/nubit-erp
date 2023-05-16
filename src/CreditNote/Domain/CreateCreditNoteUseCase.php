<?php

namespace App\CreditNote\Domain;

class CreateCreditNoteUseCase
{
    private CreditNoteRepositoryInterface $repository;

    public function __construct(CreditNoteRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(CreditNote $creditNote): CreditNote
    {
        return $this->repository->insert($creditNote);
    }
}
