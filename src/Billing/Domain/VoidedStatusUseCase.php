<?php

namespace App\Billing\Domain;

use Greenter\Model\Response\StatusCdrResult;

class VoidedStatusUseCase
{
    private VoidedRepositoryInterface $voidedRepository;

    public function __construct(VoidedRepositoryInterface $voidedRepository)
    {
        $this->voidedRepository = $voidedRepository;
    }

    public function __invoke(string $ticket, string $ruc): StatusCdrResult
    {
        return $this->voidedRepository->getStatus($ticket, $ruc);
    }
}
