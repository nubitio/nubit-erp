<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test for the DB-level append-only guard on `cash_movements`
 * (migration Version20260904140000_cash_movements_immutability_guard, closing
 * the ADR-004 amendment gap: "nothing currently stops an application-layer
 * mutation of a persisted cash movement"). Inserts directly with foreign key
 * checks disabled — this test only cares about the trigger behavior on the
 * table itself, not the referential graph. FOREIGN_KEY_CHECKS is restored
 * immediately after the insert since it's a session setting, not scoped to
 * the transaction, and would otherwise leak into later statements on the
 * same connection.
 *
 * Lets the DB assign the id via AUTO_INCREMENT rather than picking one at
 * random: phpunit.coverage.xml (used by CI and scripts/quality-check.sh)
 * does not register DAMA\DoctrineTestBundle, so this insert isn't rolled
 * back between tests, and the DELETE trigger this test verifies makes the
 * row impossible to clean up afterwards regardless — a caller-chosen random
 * id would risk a collision with a real row (or a previous test run) over
 * time, which AUTO_INCREMENT can't.
 */
uses(KernelTestCase::class);

beforeEach(function (): void {
    self::bootKernel();
    $connection = static::getContainer()->get('doctrine.dbal.tenant_connection');
    if (!$connection instanceof Connection) {
        throw new \RuntimeException('doctrine.dbal.tenant_connection must be a DBAL Connection');
    }

    $this->connection = $connection;
    $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
    $this->connection->executeStatement('INSERT INTO cash_movements (cash_session_id, movement_type, amount, description, created_by, created_at)
         VALUES (1, :type, :amount, :description, 1, NOW())', [
        'type' => 'CASH_IN',
        'amount' => '10.00',
        'description' => 'immutability trigger test',
    ]);
    $this->movementId = (int) $this->connection->lastInsertId();
    $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
});

it('rejects UPDATE on cash_movements', function (): void {
    $this->expectException(DbalException::class);
    $this->expectExceptionMessageMatches('/append-only ledger: UPDATE is not allowed/');

    $this->connection->executeStatement('UPDATE cash_movements SET amount = :amount WHERE id = :id', [
        'amount' => '20.00',
        'id' => $this->movementId,
    ]);
});

it('rejects DELETE on cash_movements', function (): void {
    $this->expectException(DbalException::class);
    $this->expectExceptionMessageMatches('/append-only ledger: DELETE is not allowed/');

    $this->connection->executeStatement('DELETE FROM cash_movements WHERE id = :id', ['id' => $this->movementId]);
});

it('still allows INSERT on cash_movements', function (): void {
    $count = $this->connection->fetchOne('SELECT COUNT(*) FROM cash_movements WHERE id = :id', ['id' => $this->movementId]);

    expect((int) $count)->toBe(1);
});
