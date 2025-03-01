<?php

namespace App\CreditNote\Domain;

class DeleteCreditNoteUseCase
{
    public function __construct(private readonly CreditNoteRepositoryInterface $repository)
    {
    }

    public function __invoke(string $id): void
    {
        $this->repository->delete($id);
    }
}
