<?php

declare(strict_types=1);

namespace App\Treasury\Repository;

use App\Accounting\Message\EconomicEvent;
use App\Treasury\Domain\GatewaySettlement;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class GatewaySettlementRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    /** @return array<string, mixed>|false */
    public function find(GatewaySettlement $settlement): array|false
    {
        return $this->connection->fetchAssociative('SELECT id, payload_hash FROM treasury_gateway_settlement WHERE clearing_account_id = ? AND reference = ?', [
            $settlement->clearingId,
            $settlement->reference,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function eligibleCaptures(GatewaySettlement $settlement): array
    {
        return $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT m.id, m.signed_amount, gl.code
            FROM treasury_movement m
            JOIN sale_payments p ON p.idempotency_key = m.source_id
            JOIN journal_entry j ON j.source = ? AND j.source_id = CAST(p.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
            JOIN journal_entry_line l ON l.journal_entry_id = j.id AND l.debit = m.signed_amount AND l.credit = 0
            JOIN chart_of_accounts gl ON gl.id = l.account_id
            WHERE m.id IN (?)
              AND m.account_id = ? AND m.currency = 'PEN'
              AND m.source_type = 'sale_payment' AND m.leg = 'main' AND m.signed_amount > 0
              AND m.occurred_at <= ?
              AND NOT EXISTS (SELECT 1 FROM treasury_gateway_settlement_capture c WHERE c.movement_id = m.id)
              AND NOT EXISTS (SELECT 1 FROM treasury_movement r WHERE r.corrects_movement_id = m.id)
            SQL, [
            EconomicEvent::SOURCE_RECEIVABLE_COLLECT,
            $settlement->captureIds,
            $settlement->clearingId,
            $settlement->paidAt->format('Y-m-d H:i:s'),
        ], [
            ParameterType::STRING,
            ArrayParameterType::STRING,
            ParameterType::STRING,
            ParameterType::STRING,
        ]);
    }

    public function save(string $id, GatewaySettlement $settlement): void
    {
        $this->connection->insert('treasury_gateway_settlement', [
            'id' => $id,
            'clearing_account_id' => $settlement->clearingId,
            'reference' => $settlement->reference,
            'payload_hash' => $settlement->fingerprint(),
        ]);
        foreach ($settlement->captureIds as $capture) {
            $this->connection->insert('treasury_gateway_settlement_capture', [
                'movement_id' => $capture,
                'settlement_id' => $id,
            ]);
        }
    }
}
