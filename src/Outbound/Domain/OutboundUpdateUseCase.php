<?php

namespace App\Outbound\Domain;

class OutboundUpdateUseCase
{
    private OutboundRepositoryInterface $repository;

    public function __construct(OutboundRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Outbound $outbound): void
    {
        $this->repository->update($outbound);
    }
}
