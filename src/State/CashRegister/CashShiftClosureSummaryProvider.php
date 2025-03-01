<?php

namespace App\State\CashRegister;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\CashRegister\CashShiftClosureSummaryDto;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class CashShiftClosureSummaryProvider implements ProviderInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object | array | null
    {
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

        $data = $this->connection->fetchAllAssociative($sql, [$cashShiftId]);

        return array_map(fn ($row): \App\Dto\CashRegister\CashShiftClosureSummaryDto => new CashShiftClosureSummaryDto(
            paymentMethodId: (int)$row['paymentMethodId'],
            paymentMethod: $row['paymentMethod'],
            expectedAmount: (float)$row['expectedAmount'],
        ), $data);
    }
}
