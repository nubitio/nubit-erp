<?php

namespace App\Inbound\Domain;

class InboundCreateUseCase
{
    private InboundRepositoryInterface $repository;

    public function __construct(InboundRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Inbound $inbound): void
    {
        $this->repository->insert($inbound);
    }
}
