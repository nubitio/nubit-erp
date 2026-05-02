<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Billing\Entity\TaxDocument;

interface CreditNoteStockReverserInterface
{
    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function reverse(TaxDocument $creditNote): void;
}
