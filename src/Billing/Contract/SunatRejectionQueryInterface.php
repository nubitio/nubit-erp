<?php

declare(strict_types=1);

namespace App\Billing\Contract;

use App\Billing\Entity\TaxDocumentEvent;

/**
 * Query contract for SUNAT rejection events exposed to consumers such as AI.
 */
interface SunatRejectionQueryInterface
{
    /**
     * Returns all TaxDocumentEvent records where the SUNAT response code is not null and not '0'
     * (i.e., actual errors/rejections), ordered by creation date descending.
     *
     * @return list<TaxDocumentEvent>
     */
    public function findRejections(): array;
}
