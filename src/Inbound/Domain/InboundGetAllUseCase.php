<?php

namespace App\Inbound\Domain;

class InboundGetAllUseCase
{
    private InboundRepositoryInterface $repository;

    public function __construct(InboundRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(?array $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}
