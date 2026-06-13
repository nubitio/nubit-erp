<?php

declare(strict_types=1);

namespace App\Billing\Despatch\Domain;

use App\Billing\Sunat\Model\Response\DocumentResponse;
use Greenter\Model\Despatch\Despatch;

interface DespatchRepositoryInterface
{
    /**
     * Envía la guía de remisión a SUNAT a través de lycet y devuelve la
     * respuesta (CDR / ticket) normalizada.
     */
    public function sendDespatch(Despatch $despatch): DocumentResponse;
}
