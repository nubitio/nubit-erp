<?php

namespace App\Outbound\Domain;

class OutboundCreateUseCase
{
    private OutboundRepositoryInterface $repository;

    public function __construct(OutboundRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Outbound $outbound): void
    {
        $this->repository->insert($outbound);
    }
}