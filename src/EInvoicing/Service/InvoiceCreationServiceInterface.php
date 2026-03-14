<?php

declare(strict_types=1);

namespace App\EInvoicing\Service;

use App\Entity\SaleRecord;
use Brick\Math\Exception\MathException;
use Doctrine\DBAL\Exception;

interface InvoiceCreationServiceInterface
{
    /**
     * @return array{success: bool, message: string, invoiceId?: string}
     *
     * @throws Exception
     * @throws MathException
     * @throws \DateMalformedStringException
     */
    public function createForSale(SaleRecord $sale): array;
}
