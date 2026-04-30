<?php

declare(strict_types=1);

namespace App\Sales\State;

use Nubit\Platform\Exception\ServiceException;
use App\Sales\Entity\SaleRecord;

interface DetractionCalculatorInterface
{
    /**
     * @throws ServiceException
     */
    public function applyFromItems(SaleRecord $sale): void;

    public function getThreshold(string $code): float;
}
