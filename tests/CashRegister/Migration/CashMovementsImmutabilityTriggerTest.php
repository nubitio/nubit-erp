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
 * table itself, not the referential graph, and the whole test runs inside
 * DAMA's rolled-back transaction so no manual cleanup is needed.
 */
uses(KernelTestCase::class);

beforeEach(function (): void {
    self::bootKernel();
    $this->connection = static::getContainer()->get('doctrine.dbal.tenant_connection');
    assert($this->connection instanceof Connection, 'doctrine.dbal.tenant_connection must be a DBAL Connection');

    $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
    $this->connection->executeStatement('INSERT INTO cash_movements (id, cash_session_id, movement_type, amount, description, created_by, created_at)
         VALUES (:id, 1, :type, :amount, :description, 1, NOW())', [
        'id' => 999_777_001,
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
        'id' => 999_777_001,
    ]);
});

it('rejects DELETE on cash_movements', function (): void {
    $this->expectException(DbalException::class);
    $this->expectExceptionMessageMatches('/append-only ledger: DELETE is not allowed/');

    $this->connection->executeStatement('DELETE FROM cash_movements WHERE id = :id', ['id' => 999_777_001]);
});

it('still allows INSERT on cash_movements', function (): void {
    $count = $this->connection->fetchOne('SELECT COUNT(*) FROM cash_movements WHERE id = :id', ['id' => 999_777_001]);

    expect((int) $count)->toBe(1);
});
