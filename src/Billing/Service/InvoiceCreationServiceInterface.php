<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Sales\Domain\RequestedVoucherType;
use App\Sales\Entity\SaleRecord;
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
    public function createForSale(SaleRecord $sale, ?RequestedVoucherType $requestedVoucherTypeOverride = null): array;
}
