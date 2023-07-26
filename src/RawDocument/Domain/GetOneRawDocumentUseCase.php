<?php

namespace App\RawDocument\Domain;

class GetOneRawDocumentUseCase
{
    private RawDocumentRepositoryInterface $repository;

    public function __construct(RawDocumentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): RawDocument
    {
        return $this->repository->getOne($id);
    }
}
