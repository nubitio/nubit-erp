<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;

final readonly class KardexCostSqlHelper
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function purchaseItemJoinSql(): string
    {
        if ($this->supportsPurchaseItemJoin()) {
            return <<<'SQL'
                LEFT JOIN goods_receipt_items gri ON sm.source_type = 'PURCHASE_ITEM'
                    AND gri.goods_receipt_id = SUBSTRING_INDEX(sm.source_id, ':', 1)
                    AND gri.product_id = sm.product_id
                    AND (
                        gri.line_no = CAST(SUBSTRING_INDEX(sm.source_id, ':', -1) AS UNSIGNED)
                        OR gri.line_no = 0
                    )
                SQL;
        }

        return <<<'SQL'
            LEFT JOIN goods_receipt_items gri ON 1 = 0
            SQL;
    }

    public function inboundUnitCostExpression(): string
    {
        return 'COALESCE(sl.cost, gri.unit_cost, p.precio_compra, 0)';
    }

    private function supportsPurchaseItemJoin(): bool
    {
        return !$this->connection->getDatabasePlatform() instanceof SQLitePlatform;
    }
}
