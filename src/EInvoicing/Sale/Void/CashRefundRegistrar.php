<?php

declare(strict_types=1);

namespace App\EInvoicing\Sale\Void;

use App\Core\Util\Money;
use App\Entity\CashMovementCategory;
use App\Entity\PaymentMethod;
use App\Entity\SaleRecord;
use App\State\Sale\CashSessionResolverInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

final readonly class CashRefundRegistrar
{
    public function __construct(
        private Connection $connection,
        private CashSessionResolverInterface $cashSessionResolver,
        private LoggerInterface $logger,
    ) {
    }

    public function register(SaleRecord $sale, int $userId): void
    {
        if ($sale->getTipoPago()?->getId() !== PaymentMethod::CASH) {
            return;
        }

        $cashSessionId = $this->cashSessionResolver->tryResolveForSale($sale);

        if ($cashSessionId === null) {
            $this->logger->warning('No cash session found for cash refund movement — skipping', [
                'sale_id' => $sale->getId(),
                'user_id' => $userId,
            ]);

            return;
        }

        $category = $this->connection->fetchAssociative(
            'SELECT id FROM cash_movement_category WHERE code = :code LIMIT 1',
            ['code' => CashMovementCategory::CUSTOMER_REFUND],
        );

        if ($category === false) {
            $this->logger->error('Cash refund category not found in cash_movement_category', [
                'code' => CashMovementCategory::CUSTOMER_REFUND,
                'sale_id' => $sale->getId(),
            ]);

            return;
        }

        $saleId   = $sale->getId();
        $serie    = $sale->getSerie() ?? '';
        $numero   = $sale->getNumero() ?? '';
        $amount   = Money::toString(Money::money($sale->getTotal() ?? '0'));
        $now      = new DateTimeImmutable();
        $seed     = sprintf('cash-refund:%s:%d', $saleId, $cashSessionId);
        $hex      = md5($seed);
        $uuid     = sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
        $idemHex  = md5('idem:' . $seed);
        $idemKey  = sprintf(
            '%s-%s-%s-%s-%s',
            substr($idemHex, 0, 8),
            substr($idemHex, 8, 4),
            substr($idemHex, 12, 4),
            substr($idemHex, 16, 4),
            substr($idemHex, 20, 12),
        );
        $nowSql   = $now->format('Y-m-d H:i:s');

        $this->connection->executeStatement(
            <<<'SQL'
INSERT INTO cash_movements (
    cash_session_id,
    movement_type,
    category_id,
    sale_id,
    return_id,
    payment_method_id,
    amount,
    description,
    created_by,
    created_at,
    local_uuid,
    idempotency_key,
    sync_status,
    synced_at,
    device_id,
    version_no,
    last_modified_at
) VALUES (
    :cash_session_id,
    'REFUND_OUT',
    :category_id,
    :sale_id,
    NULL,
    :payment_method_id,
    :amount,
    :description,
    :created_by,
    :created_at,
    :local_uuid,
    :idempotency_key,
    'SYNCED',
    :synced_at,
    NULL,
    1,
    :last_modified_at
)
ON DUPLICATE KEY UPDATE
    amount           = VALUES(amount),
    description      = VALUES(description),
    synced_at        = VALUES(synced_at),
    version_no       = version_no + 1,
    last_modified_at = VALUES(last_modified_at)
SQL,
            [
                'cash_session_id'   => $cashSessionId,
                'category_id'       => (int) $category['id'],
                'sale_id'           => $saleId,
                'payment_method_id' => PaymentMethod::CASH,
                'amount'            => $amount,
                'description'       => 'Anulación de venta ' . $serie . '-' . $numero,
                'created_by'        => $userId,
                'created_at'        => $nowSql,
                'local_uuid'        => $uuid,
                'idempotency_key'   => $idemKey,
                'synced_at'         => $nowSql,
                'last_modified_at'  => $nowSql,
            ]
        );
    }
}
