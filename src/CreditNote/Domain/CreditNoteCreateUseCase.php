<?php

namespace App\CreditNote\Domain;

class CreditNoteCreateUseCase
{
    private CreditNoteRepositoryInterface $repository;

    public function __construct(CreditNoteRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(CreditNote $creditNote): void
    {
        $this->repository->insert($creditNote);
    }
}