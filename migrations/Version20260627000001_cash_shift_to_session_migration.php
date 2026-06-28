<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260627000001_cash_shift_to_session_migration extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'SPEC-007: add UUID columns to cash_sessions/cash_movements, create cash_reconciliations. MariaDB.';
    }

    public function up(Schema $schema): void
    {
        if ($this->isAlreadyApplied()) {
            return;
        }

        $this->write('Applying SPEC-007 cash schema migration…');

        $this->exec('SET FOREIGN_KEY_CHECKS = 0');

        $this->addCashSessionUuidColumns();
        $this->backfillSessionUuids();
        $this->morphCashMovements();
        $this->backfillMovementUuids();
        $this->addRegisterUuidToCashSessions();
        $this->createCashReconciliations();
        $this->addRequiredIndexes();
        $this->logBackfillCounts();

        $this->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(Schema $schema): void
    {
        $this->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->exec('DROP TABLE IF EXISTS cash_reconciliations');
        $this->exec('ALTER TABLE cash_movements DROP COLUMN IF EXISTS movement_uuid');
        $this->exec('ALTER TABLE cash_movements DROP COLUMN IF EXISTS session_uuid');
        $this->exec('ALTER TABLE cash_movements DROP COLUMN IF EXISTS registered_by_user_id');
        $this->exec('ALTER TABLE cash_movements DROP COLUMN IF EXISTS corrects_movement_id');
        $this->exec('ALTER TABLE cash_movements DROP COLUMN IF EXISTS occurred_at');
        $this->exec('ALTER TABLE cash_sessions DROP COLUMN IF EXISTS session_uuid');
        $this->exec('ALTER TABLE cash_sessions DROP COLUMN IF EXISTS register_uuid');
        $this->exec('ALTER TABLE cash_sessions DROP COLUMN IF EXISTS opened_by_user_uuid');
        $this->exec('ALTER TABLE cash_sessions DROP COLUMN IF EXISTS closed_by_user_uuid');
        $this->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function exec(string $sql, array $params = []): void
    {
        $this->connection->executeStatement($sql, $params);
    }

    private function isAlreadyApplied(): bool
    {
        return count($this->connection->fetchFirstColumn(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'session_uuid'"
        )) > 0;
    }

    private function addCashSessionUuidColumns(): void
    {
        $cols = $this->getColumns('cash_sessions');

        if (!in_array('session_uuid', $cols, true)) {
            $this->exec('ALTER TABLE cash_sessions ADD COLUMN session_uuid CHAR(36) NULL AFTER id');
        }
        if (!in_array('register_uuid', $cols, true)) {
            $this->exec('ALTER TABLE cash_sessions ADD COLUMN register_uuid CHAR(36) NULL AFTER cash_register_id');
        }
        if (!in_array('opened_by_user_uuid', $cols, true)) {
            $this->exec('ALTER TABLE cash_sessions ADD COLUMN opened_by_user_uuid CHAR(36) NULL AFTER opened_by');
        }
        if (!in_array('closed_by_user_uuid', $cols, true)) {
            $this->exec('ALTER TABLE cash_sessions ADD COLUMN closed_by_user_uuid CHAR(36) NULL AFTER closed_by');
        }
    }

    private function backfillSessionUuids(): void
    {
        $nullCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM cash_sessions WHERE session_uuid IS NULL');
        if ($nullCount === 0) {
            return;
        }

        $rows = $this->connection->fetchAllAssociative('SELECT id, cash_register_id, opened_by, closed_by FROM cash_sessions WHERE session_uuid IS NULL');

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $params = [
                'session_uuid' => $this->intToUuid($id),
                'reg_uuid' => $this->intToUuid((int) $row['cash_register_id']),
                'opened_uuid' => $this->intToUuid((int) $row['opened_by']),
                'id' => $id,
            ];
            $set = 'session_uuid = :session_uuid, register_uuid = :reg_uuid, opened_by_user_uuid = :opened_uuid';
            if ($row['closed_by'] !== null) {
                $set .= ', closed_by_user_uuid = :closed_uuid';
                $params['closed_uuid'] = $this->intToUuid((int) $row['closed_by']);
            }
            $this->exec("UPDATE cash_sessions SET {$set} WHERE id = :id", $params);
        }
    }

    private function morphCashMovements(): void
    {
        $cols = $this->getColumns('cash_movements');

        if (!in_array('movement_uuid', $cols, true)) {
            $this->exec('ALTER TABLE cash_movements ADD COLUMN movement_uuid CHAR(36) NULL FIRST');
        }
        if (!in_array('session_uuid', $cols, true)) {
            $this->exec('ALTER TABLE cash_movements ADD COLUMN session_uuid CHAR(36) NULL AFTER cash_session_id');
        }
        if (!in_array('registered_by_user_id', $cols, true)) {
            $this->exec('ALTER TABLE cash_movements ADD COLUMN registered_by_user_id CHAR(36) NULL AFTER created_by');
        }
        if (!in_array('corrects_movement_id', $cols, true)) {
            $this->exec('ALTER TABLE cash_movements ADD COLUMN corrects_movement_id CHAR(36) NULL AFTER description');
        }
        if (!in_array('occurred_at', $cols, true)) {
            $this->exec("ALTER TABLE cash_movements ADD COLUMN occurred_at DATETIME(6) NULL AFTER description");
        }
    }

    private function backfillMovementUuids(): void
    {
        $nullCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM cash_movements WHERE movement_uuid IS NULL');
        if ($nullCount === 0) {
            return;
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT cm.id, cm.cash_session_id, cm.created_by, cs.session_uuid
             FROM cash_movements cm
             LEFT JOIN cash_sessions cs ON cs.id = cm.cash_session_id
             WHERE cm.movement_uuid IS NULL'
        );

        foreach ($rows as $row) {
            $sessionUuid = $row['session_uuid'] ?? $this->intToUuid((int) $row['cash_session_id']);
            $this->exec(
                'UPDATE cash_movements SET movement_uuid = :movement_uuid, session_uuid = :session_uuid, registered_by_user_id = :registered_by, occurred_at = COALESCE(occurred_at, created_at) WHERE id = :id',
                [
                    'movement_uuid' => $this->generateUuid(),
                    'session_uuid' => $sessionUuid,
                    'registered_by' => $this->intToUuid((int) $row['created_by']),
                    'id' => (int) $row['id'],
                ]
            );
        }
    }

    private function addRegisterUuidToCashSessions(): void
    {
        $this->exec(
            'UPDATE cash_sessions SET register_uuid = COALESCE(register_uuid, session_uuid) WHERE register_uuid IS NULL'
        );
    }

    private function createCashReconciliations(): void
    {
        $this->exec('CREATE TABLE IF NOT EXISTS cash_reconciliations (
            id CHAR(36) NOT NULL,
            session_id CHAR(36) NOT NULL,
            note TEXT NOT NULL,
            reconciled_by_user_id CHAR(36) NOT NULL,
            reconciled_at DATETIME(6) NOT NULL,
            created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            PRIMARY KEY (id),
            INDEX idx_cash_reconciliations_session (session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    private function addRequiredIndexes(): void
    {
        $this->exec('CREATE INDEX IF NOT EXISTS idx_cash_sessions_session_uuid ON cash_sessions (session_uuid)');
        $this->exec('CREATE INDEX IF NOT EXISTS idx_cash_movements_movement_uuid ON cash_movements (movement_uuid)');
        $this->exec('CREATE INDEX IF NOT EXISTS idx_cash_movements_session_uuid ON cash_movements (session_uuid)');
    }

    private function logBackfillCounts(): void
    {
        $sessions = $this->connection->fetchOne('SELECT COUNT(*) FROM cash_sessions WHERE session_uuid IS NOT NULL');
        $movements = $this->connection->fetchOne('SELECT COUNT(*) FROM cash_movements WHERE movement_uuid IS NOT NULL');
        $this->write(sprintf('  → %d sessions, %d movements backfilled', (int) $sessions, (int) $movements));
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function intToUuid(int $id): string
    {
        return sprintf('00000000-0000-7000-0000-%012d', $id);
    }

    private function getColumns(string $table): array
    {
        return array_map(
            fn(array $row): string => $row['COLUMN_NAME'],
            $this->connection->fetchAllAssociative(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table",
                ['table' => $table]
            )
        );
    }
}
