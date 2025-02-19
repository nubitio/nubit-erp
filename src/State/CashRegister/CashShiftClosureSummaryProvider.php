<?php

namespace App\State\CashRegister;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class CashShiftClosureSummaryProvider implements ProviderInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @throws Exception
     */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): object|array|null {
        $cashShiftId = $uriVariables['id'];

        $sql = <<<SQL
SELECT
    pm.id AS paymentMethodId,
    pm.name AS paymentMethod,
    COALESCE(SUM(IF(cm.type = 'income', cm.amount, 0)), 0) -
    COALESCE(SUM(IF(cm.type = 'expense', cm.amount, 0)), 0)
    AS expectedAmount
FROM cash_movement cm
JOIN payment_method pm ON cm.payment_method_id = pm.id
WHERE cm.cash_shift_id = ?
GROUP BY pm.id
SQL;

        return $this->connection->fetchAllAssociative($sql, [$cashShiftId]);
    }
}
