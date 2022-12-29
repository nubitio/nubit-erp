<?php

namespace App\Billing\Domain;

use App\Billing\Model\Response\StatusResponse;

class VoidedStatusUseCase
{
    private VoidedRepositoryInterface $voidedRepository;

    public function __construct(VoidedRepositoryInterface $voidedRepository)
    {
        $this->voidedRepository = $voidedRepository;
    }

    public function __invoke(string $ticket, string $ruc): StatusResponse
    {
        return $this->voidedRepository->getStatus($ticket, $ruc);
    }
}
