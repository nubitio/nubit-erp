<?php

namespace App\CreditNote\Domain;

class CreateCreditNoteUseCase
{
    public function __construct(private readonly CreditNoteRepositoryInterface $repository)
    {
    }

    public function __invoke(CreditNote $creditNote): CreditNote
    {
        return $this->repository->insert($creditNote);
    }
}
