<?php

namespace App\Outbound\Domain;

class OutboundDeleteUseCase
{
    private OutboundRepositoryInterface $repository;

    public function __construct(OutboundRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): void
    {
        $this->repository->delete($id);
    }
}