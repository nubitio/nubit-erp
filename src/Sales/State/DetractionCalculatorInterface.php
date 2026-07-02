<?php

declare(strict_types=1);

namespace App\Sales\State;

use App\Sales\Entity\SaleRecord;
use Nubit\Platform\Exception\ServiceException;

interface DetractionCalculatorInterface
{
    /**
     * @throws ServiceException
     */
    public function applyFromItems(SaleRecord $sale): void;

    public function getThreshold(string $code): float;
}
