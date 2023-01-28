<?php

namespace App\Consult\Domain;

readonly class ConsultUseCase
{
    public function __construct(
        private ConsultRepositoryInterface $consultRepository
    ) {
    }

    public function consult(string $id, DocType $docType): Consult
    {
        return $this->consultRepository->consult($id, $docType);
    }
}