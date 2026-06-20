<?php

declare(strict_types=1);

namespace App\Inventory\State;

use Override;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Inventory\Dto\PriceListDto;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Returns all active products with their effective price for a given price list.
 *
 * Priority order:
 *   1. Explicit override in price_list_detail (highest priority)
 *   2. base_price × price_list.multiplier     (when multiplier is set)
 *   3. product base price                      (fallback — original behaviour)
 *
 * @implements ProviderInterface<object>
 */
readonly class PriceListProvider implements ProviderInterface
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
                p.producto_id                                        AS id,
                p.descripcion                                        AS name,
                CASE
                    WHEN pld.price IS NOT NULL
                        THEN pld.price
                    WHEN pl.multiplier IS NOT NULL
                        THEN ROUND(p.precio * pl.multiplier, 4)
                    ELSE p.precio
                END                                                  AS price,
                CASE
                    WHEN pld.price IS NOT NULL THEN \'override\'
                    WHEN pl.multiplier IS NOT NULL THEN \'multiplier\'
                    ELSE \'base\'
                END                                                  AS price_source
            FROM producto p
            CROSS JOIN price_list pl
                ON pl.id = :id
            LEFT JOIN price_list_detail pld
                ON pld.product_id   = p.producto_id
               AND pld.price_list_id = pl.id
            WHERE p.is_active = 1
            ORDER BY p.descripcion';

        $data = $this->connection->fetchAllAssociative($sql, ['id' => $id]);

        return array_map(
            fn (array $item): PriceListDto => new PriceListDto(
                id: $item['id'],
                name: $item['name'],
                price: $item['price'],          // kept as string — exact decimal from DB
                priceSource: $item['price_source'],
            ),
            $data
        );
    }
}
