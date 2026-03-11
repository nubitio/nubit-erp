<?php

declare(strict_types=1);

namespace App\State\Sale;

use App\Core\Exception\ServiceException;
use App\Entity\Sale;

interface DetractionCalculatorInterface
{
    /**
     * @throws ServiceException
     */
    public function applyFromItems(Sale $sale): void;

    public function getThreshold(string $code): float;
}
