<?php

namespace App\RawDocument\Domain;

class UpdateRawDocumentUseCase
{
    private RawDocumentRepositoryInterface $repository;

    public function __construct(RawDocumentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(RawDocument $rawDocument): RawDocument
    {
        return $this->repository->update($rawDocument);
    }
}
