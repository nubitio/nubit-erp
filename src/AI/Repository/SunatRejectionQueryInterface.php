<?php

declare(strict_types=1);

namespace App\AI\Repository;

use App\Entity\TaxDocumentEvent;

/**
 * Query contract for SUNAT rejection events used by the AI assistant.
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
