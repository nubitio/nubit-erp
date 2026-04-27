<?php

declare(strict_types=1);

namespace App\State\Sale;

use App\Core\Exception\ServiceException;
use App\Sales\Entity\SaleRecord;

interface DetractionCalculatorInterface
{
    /**
     * @throws ServiceException
     */
    public function applyFromItems(SaleRecord $sale): void;

    public function getThreshold(string $code): float;
}
