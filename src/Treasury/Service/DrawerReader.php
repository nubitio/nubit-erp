<?php

declare(strict_types=1);

namespace App\Treasury\Service;

use App\Efact\Domain\Money;
use Doctrine\DBAL\Connection;

/**
 * Read-only view of the cash drawers. Drawers keep their own ledger (`cash_movements`, bound to a session) and
 * Treasury never copies it (D-A). A drawer's balance is therefore read the way the session close computes it:
 * the session's opening amount plus the inflows minus the outflows. The `OPENING` row is only an audit row and
 * is not counted.
 */
final readonly class DrawerReader
{
    public function __construct(
        private Connection $connection,
    ) {}

    /** Cash register the user currently has an open session on, if any. */
    public function openRegisterIdFor(int $userId): ?int
    {
        $id = $this->connection->fetchOne("SELECT cash_register_id FROM cash_sessions WHERE opened_by = :user AND status = 'OPEN' ORDER BY opened_at DESC LIMIT 1", [
            'user' => $userId,
        ]);

        return $id === false ? null : (int) $id;
    }

    /** @return array{sessionId: int, registerId: int}|null the user's open session and the register it belongs to */
    public function openSessionFor(int $userId): ?array
    {
        $row = $this->connection->fetchAssociative(
            "SELECT id, cash_register_id FROM cash_sessions WHERE opened_by = :user AND status = 'OPEN' ORDER BY opened_at DESC LIMIT 1",
            ['user' => $userId],
        );

        return (
            $row === false ? null : ['sessionId' => (int) $row['id'], 'registerId' => (int) $row['cash_register_id']]
        );
    }

    /**
     * Cash in the drawer: the open session's expected amount; with no open session, what the last session
     * counted when it closed.
     */
    public function balanceOf(int $cashRegisterId): string
    {
        $session = $this->connection->fetchAssociative(
            'SELECT id, status, opening_amount, counted_amount, expected_amount FROM cash_sessions
             WHERE cash_register_id = :register ORDER BY opened_at DESC, id DESC LIMIT 1',
            ['register' => $cashRegisterId],
        );
        if ($session === false) {
            return '0.00';
        }

        if ((string) $session['status'] !== 'OPEN') {
            return Money::toString(Money::money(
                (string) ($session['counted_amount'] ?? $session['expected_amount'] ?? '0'),
            ));
        }

        $net = $this->connection->fetchOne("SELECT COALESCE(SUM(CASE
                WHEN movement_type IN ('SALE_IN', 'CASH_IN') THEN amount
                WHEN movement_type IN ('REFUND_OUT', 'CASH_OUT') THEN -amount
                ELSE 0 END), 0)
             FROM cash_movements WHERE cash_session_id = :session", [
            'session' => $session['id'],
        ]);

        return Money::toString(Money::money((string) $session['opening_amount'])->plus(Money::money((string) $net)));
    }
}
