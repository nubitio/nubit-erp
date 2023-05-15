<?php

namespace App\Consult\Domain;

interface ConsultRepositoryInterface
{
    public function consult(string $id, DocType $docType): Consult;
}
