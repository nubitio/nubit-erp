<?php

declare(strict_types=1);

namespace App\Inventory\State;

use Override;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Inventory\Dto\PriceListApplyDto;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Provider for GET /api/price-lists/{id}/apply.
 *
 * Returns a flat map of { productId → effectivePrice } for all active products
 * so that the sale form can quickly look up the tariff price for each line item.
 *
 * Effective price priority:
 *   1. Explicit row in price_list_detail            (highest)
 *   2. base_price × price_list.multiplier           (when multiplier is set)
 *   3. Product base price                           (fallback)
 *
 * @implements ProviderInterface<object>
 */
readonly class PriceListApplyProvider implements ProviderInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object | array | null
    {
        $id = $uriVariables['id'];

        $sql = '
            SELECT
                p.producto_id AS id,
                CASE
                    WHEN pld.price IS NOT NULL
                        THEN CAST(pld.price AS CHAR)
                    WHEN pl.multiplier IS NOT NULL
                        THEN CAST(ROUND(p.precio * pl.multiplier, 4) AS CHAR)
                    ELSE CAST(p.precio AS CHAR)
                END AS price
            FROM producto p
            CROSS JOIN price_list pl ON pl.id = :id
            LEFT JOIN price_list_detail pld
                ON pld.product_id    = p.producto_id
               AND pld.price_list_id = pl.id
            WHERE p.is_active = 1';

        $data = $this->connection->fetchAllAssociative($sql, ['id' => $id]);

        return array_map(
            fn (array $row): PriceListApplyDto => new PriceListApplyDto(
                id: $row['id'],
                price: $row['price'],
            ),
            $data
        );
    }
}
