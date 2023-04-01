<?php

namespace App\Outbound\Domain;

class OutboundGetAllUseCase
{
    private OutboundRepositoryInterface $repository;

    public function __construct(OutboundRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param array<int, mixed>|null $filter
     * @return array<string, mixed>
     */
    public function __invoke(?array $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}
