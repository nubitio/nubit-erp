<?php

declare(strict_types=1);

namespace App\Billing\Sale\Void;

use App\Catalog\Entity\PaymentMethod;
use App\Sales\Entity\SaleRecord;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Port\RecordsMoneyMovement;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

/**
 * Voiding a sale returns the money. Cash goes back through {@see CashRefundRegistrar}; the card, Yape or transfer
 * payments recorded in Treasury are reversed here, each on the account it landed in (never on whatever the
 * mapping says today). A payment Treasury never saw (a sale made before it, or with it off) has nothing to
 * reverse and is skipped. Runs inside the void's transaction.
 *
 * Collections made later against a credit sale are not reversed here: they are separate payments, handled with
 * the receivable side of the void.
 */
final readonly class TreasuryRefunds
{
    public function __construct(
        private Connection $connection,
        private RecordsMoneyMovement $treasury,
    ) {}

    public function reverseNonCashPayments(SaleRecord $sale, int $userId): void
    {
        /** @var list<string> $keys */
        $keys = $this->connection->fetchFirstColumn(
            'SELECT idempotency_key FROM sale_payments WHERE sale_id = :sale AND payment_method_id <> :cash AND idempotency_key IS NOT NULL',
            ['sale' => $sale->getId(), 'cash' => PaymentMethod::CASH],
        );

        $now = new DateTimeImmutable();
        $serie = $sale->getSerie() ?? '';
        $numero = $sale->getNumero() ?? '';
        $label =
            $serie !== '' && $numero !== ''
                ? sprintf('Anulación de venta %s-%s', $serie, $numero)
                : 'Anulación de venta';
        foreach ($keys as $key) {
            $this->treasury->reverse(
                new MovementKey(MovementSource::SalePayment, $key),
                new MovementKey(MovementSource::SalePaymentReversal, $key),
                $now,
                $userId,
                $label,
            );
        }
    }
}
