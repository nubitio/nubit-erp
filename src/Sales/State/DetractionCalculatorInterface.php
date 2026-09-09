<?php

declare(strict_types=1);

namespace App\Sales\State;

use App\Efact\Extension\AsExtensionPoint;
use App\Sales\Entity\SaleRecord;
use Nubit\Platform\Exception\ServiceException;

#[AsExtensionPoint(
    id: 'sales.detraction_calculator',
    summary: 'Detraction (SPOT) thresholds and how they are applied to a sale.',
    default: DetractionCalculator::class,
    resolution: AsExtensionPoint::RESOLUTION_AUTOWIRE,
)]
interface DetractionCalculatorInterface
{
    /**
     * @throws ServiceException
     */
    public function applyFromItems(SaleRecord $sale): void;

    public function getThreshold(string $code): float;
}
