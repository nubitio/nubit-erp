<?php

namespace App\CreditNote\Domain;

class CreditNoteGetAllUseCase
{
    private CreditNoteRepositoryInterface $repository;

    public function __construct(CreditNoteRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param array<int, array<string, mixed>>|null $filter
     *
     * @return array<string, mixed>
     */
    public function __invoke(?array $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}
