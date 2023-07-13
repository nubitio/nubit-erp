<?php

namespace App\Inbound\Domain;

class InboundUpdateUseCase
{
    private InboundRepositoryInterface $repository;

    public function __construct(InboundRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Inbound $inbound): void
    {
        $this->repository->update($inbound);
    }
}
