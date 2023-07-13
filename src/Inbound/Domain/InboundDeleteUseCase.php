<?php

namespace App\Inbound\Domain;

class InboundDeleteUseCase
{
    private InboundRepositoryInterface $repository;

    public function __construct(InboundRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): void
    {
        $this->repository->delete($id);
    }
}
