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
 * table itself, not the referential graph.
 *
 * Uses a random id per run rather than relying on transactional rollback:
 * phpunit.coverage.xml (used by CI and scripts/quality-check.sh) does not
 * register DAMA\DoctrineTestBundle, so inserts here are not rolled back
 * between tests — and the DELETE trigger this test verifies makes the row
 * impossible to clean up afterwards regardless. This mirrors the
 * random-value pattern already used by other integration tests (e.g.
 * makeAdminUser() in GenerateRecurringSalesCommandTest.php) for the same
 * reason.
 */
uses(KernelTestCase::class);

beforeEach(function (): void {
    self::bootKernel();
    $this->connection = static::getContainer()->get('doctrine.dbal.tenant_connection');
    assert($this->connection instanceof Connection, 'doctrine.dbal.tenant_connection must be a DBAL Connection');

    $this->movementId = random_int(900_000_000, 999_999_999);
    $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
    $this->connection->executeStatement('INSERT INTO cash_movements (id, cash_session_id, movement_type, amount, description, created_by, created_at)
         VALUES (:id, 1, :type, :amount, :description, 1, NOW())', [
        'id' => $this->movementId,
        'type' => 'CASH_IN',
        'amount' => '10.00',
        'description' => 'immutability trigger test',
    ]);
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
