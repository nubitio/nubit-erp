<?php

namespace App\RawDocument\Domain;

class DeleteRawDocumentUseCase
{
    private RawDocumentRepositoryInterface $repository;

    public function __construct(RawDocumentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
