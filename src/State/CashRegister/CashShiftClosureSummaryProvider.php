<?php

namespace App\State\CashRegister;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\CashRegister\CashShiftClosureSummaryDto;
use App\Entity\PaymentMethod;
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

        $expectedAmount = (float) $this->connection->fetchOne(
            "
            SELECT
                COALESCE(SUM(IF(cm.type = 'income', cm.amount, 0)), 0) -
                COALESCE(SUM(IF(cm.type = 'expense', cm.amount, 0)), 0)
            FROM cash_movement cm
            WHERE cm.cash_shift_id = ?
            ",
            [$cashShiftId]
        );

        $paymentMethodName = $this->connection->fetchOne(
            'SELECT name FROM payment_method WHERE id = ?',
            [PaymentMethod::CASH]
        ) ?: 'Efectivo';

        return [
            new CashShiftClosureSummaryDto(
                paymentMethodId: PaymentMethod::CASH,
                paymentMethod: $paymentMethodName,
                expectedAmount: $expectedAmount,
            ),
        ];
    }
}
