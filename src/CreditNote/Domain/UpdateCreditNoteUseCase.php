<?php

namespace App\CreditNote\Domain;

class UpdateCreditNoteUseCase
{
    public function __construct(private readonly CreditNoteRepositoryInterface $repository)
    {
    }

    public function __invoke(CreditNote $creditNote): CreditNote
    {
        return $this->repository->update($creditNote);
    }
}
