<?php

declare(strict_types=1);

namespace App\Inventory\Core\Service;

use App\Inventory\Core\Contract\UnitConversionServiceInterface;
use Doctrine\DBAL\Connection;

final readonly class UnitConversionService implements UnitConversionServiceInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function toBase(string $productId, int $unitId, string $qty): string
    {
        $factor = $this->resolveFactor($productId, $unitId);

        return number_format((float) $qty * (float) $factor, 6, '.', '');
    }

    public function fromBase(string $productId, int $unitId, string $qtyBase): string
    {
        $factor = $this->resolveFactor($productId, $unitId);
        if ((float) $factor === 0.0) {
            throw new \RuntimeException('Invalid unit conversion factor: 0');
        }

        return number_format((float) $qtyBase / (float) $factor, 6, '.', '');
    }

    private function resolveFactor(string $productId, int $unitId): string
    {
        $factor = $this->connection->fetchOne(
            <<<'SQL'
                SELECT conversion_to_base
                FROM product_units
                WHERE product_id = :product_id
                  AND unit_id = :unit_id
                LIMIT 1
                SQL,
            [
                'product_id' => $productId,
                'unit_id' => $unitId,
            ],
        );

        if ($factor === false) {
            throw new \RuntimeException(sprintf(
                'No conversion configured for product=%s unit=%d',
                $productId,
                $unitId,
            ));
        }

        return (string) $factor;
    }
}
