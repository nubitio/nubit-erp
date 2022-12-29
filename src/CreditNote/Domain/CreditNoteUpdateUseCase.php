<?php

namespace App\CreditNote\Domain;

class CreditNoteUpdateUseCase
{
    private CreditNoteRepositoryInterface $repository;

    public function __construct(CreditNoteRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(CreditNote $creditNote): void
    {
        $this->repository->update($creditNote);
    }
}
