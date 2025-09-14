<?php

namespace App\Billing\Domain;

use Greenter\Model\Response\StatusCdrResult;

readonly class VoidedStatusUseCase
{
    public function __construct(private VoidedRepositoryInterface $voidedRepository)
    {
    }

    public function __invoke(string $ticket, string $ruc): StatusCdrResult
    {
        return $this->voidedRepository->getStatus($ticket, $ruc);
    }
}
