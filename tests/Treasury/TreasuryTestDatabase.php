<?php

declare(strict_types=1);

namespace App\Tests\Treasury;

use App\CashRegister\Service\CashMovementAppender;
use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\TreasuryAccount;
use App\Treasury\Repository\TreasuryAccountRepository;
use App\Treasury\Repository\TreasuryMovementRepository;
use App\Treasury\Service\DrawerReader;
use App\Treasury\Service\DrawerRecorder;
use App\Treasury\Service\MoneyAccountResolver;
use App\Treasury\Service\MovementReverser;
use App\Treasury\Service\TreasuryMovementRecorder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/** In-memory tables shaped like the migration's (plus the two cash tables Treasury reads), and a wired-up kit. */
final class TreasuryTestDatabase
{
    public const string BRANCH = '11111111-1111-1111-1111-111111111111';

    public Connection $db;
    public TreasuryAccountRepository $accounts;
    public TreasuryMovementRepository $movements;
    public DrawerReader $drawers;
    public TreasuryMovementRecorder $recorder;
    public CashMovementAppender $cash;

    private function __construct()
    {
        $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        foreach (self::SCHEMA as $sql) {
            $this->db->executeStatement($sql);
        }
        $this->accounts = new TreasuryAccountRepository($this->db);
        $this->movements = new TreasuryMovementRepository($this->db);
        $this->drawers = new DrawerReader($this->db);
        $this->cash = new CashMovementAppender($this->db);
        $drawer = new DrawerRecorder($this->cash, $this->drawers);
        $this->recorder = new TreasuryMovementRecorder(
            new MoneyAccountResolver($this->accounts, $this->drawers),
            $this->movements,
            $drawer,
            new MovementReverser($this->movements, $this->accounts, $this->cash, $this->drawers),
        );
    }

    public static function create(): self
    {
        return new self();
    }

    public function account(
        string $id,
        AccountKind $kind = AccountKind::Bank,
        string $currency = 'PEN',
        ?string $name = null,
        ?int $cashRegisterId = null,
        bool $active = true,
        ?string $bankAccountId = null,
    ): TreasuryAccount {
        $account = new TreasuryAccount(
            $id,
            $kind,
            $name ?? ucfirst($kind->value) . ' ' . $id,
            $currency,
            self::BRANCH,
            $cashRegisterId,
            $bankAccountId ?? ($kind === AccountKind::Bank ? 'bank-' . $id : null),
            $active,
        );
        $this->accounts->add($account);

        return $account;
    }

    public function openSession(int $id, int $registerId, int $userId, string $openingAmount): void
    {
        $this->db->insert('cash_sessions', [
            'id' => $id,
            'cash_register_id' => $registerId,
            'opened_by' => $userId,
            'status' => 'OPEN',
            'opening_amount' => $openingAmount,
            'opened_at' => '2026-11-18 08:00:00',
        ]);
    }

    public function cashMovement(int $sessionId, string $type, string $amount): void
    {
        $this->db->insert('cash_movements', [
            'cash_session_id' => $sessionId,
            'movement_type' => $type,
            'amount' => $amount,
        ]);
    }

    private const array SCHEMA = [
        'CREATE TABLE treasury_account (
            id TEXT PRIMARY KEY, kind TEXT NOT NULL, name TEXT NOT NULL, currency TEXT NOT NULL, branch_id TEXT NULL,
            cash_register_id INTEGER NULL UNIQUE, bank_account_id TEXT NULL UNIQUE, active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL)',
        'CREATE TABLE treasury_movement (
            id TEXT PRIMARY KEY, account_id TEXT NOT NULL, signed_amount NUMERIC NOT NULL CHECK (signed_amount <> 0), currency TEXT NOT NULL,
            occurred_on TEXT NOT NULL, occurred_at TEXT NOT NULL, source_type TEXT NOT NULL, source_id TEXT NOT NULL, leg TEXT NOT NULL DEFAULT \'main\',
            party_type TEXT NULL, party_id TEXT NULL, reference TEXT NULL, description TEXT NULL, transfer_id TEXT NULL,
            corrects_movement_id TEXT NULL, origin TEXT NOT NULL DEFAULT \'live\', created_by INTEGER NULL, created_at TEXT NOT NULL,
            UNIQUE (source_type, source_id, leg))',
        'CREATE TABLE treasury_payment_method_account (
            branch_id TEXT NOT NULL, payment_method_id INTEGER NOT NULL, currency TEXT NOT NULL, account_id TEXT NOT NULL, PRIMARY KEY (branch_id, payment_method_id, currency))',
        'CREATE TABLE cash_sessions (
            id INTEGER PRIMARY KEY, cash_register_id INTEGER NOT NULL, opened_by INTEGER NOT NULL, status TEXT NOT NULL,
            opening_amount NUMERIC NOT NULL DEFAULT 0, counted_amount NUMERIC NULL, expected_amount NUMERIC NULL, opened_at TEXT NOT NULL)',
        'CREATE TABLE local (local_id TEXT PRIMARY KEY, descripcion TEXT NOT NULL, is_active INTEGER NOT NULL DEFAULT 1)',
        'CREATE TABLE cash_register (id INTEGER PRIMARY KEY, branch_id TEXT NOT NULL, name TEXT NOT NULL, is_active INTEGER NOT NULL DEFAULT 1)',
        'CREATE TABLE bank_account (id TEXT PRIMARY KEY, alias TEXT NOT NULL, currency TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT \'2026-01-01 00:00:00\')',
        'CREATE TABLE payment_method (id INTEGER PRIMARY KEY, name TEXT NOT NULL, is_active INTEGER NOT NULL DEFAULT 1, settlement_behavior TEXT NOT NULL DEFAULT \'COLLECTED\')',
        'CREATE TABLE sales (id TEXT PRIMARY KEY, series TEXT NULL, number TEXT NULL, branch_id TEXT NULL, customer_id INTEGER NULL, status TEXT NULL, currency TEXT NULL, due_date TEXT NULL, balance_due NUMERIC NULL, receivable_account INTEGER NULL, deleted_at TEXT NULL)',
        'CREATE TABLE sale_payments (id INTEGER PRIMARY KEY AUTOINCREMENT, sale_id TEXT NOT NULL, payment_method_id INTEGER NOT NULL, amount NUMERIC NULL, currency TEXT NULL, paid_at TEXT NULL, idempotency_key TEXT NULL, reference TEXT NULL)',
        'CREATE TABLE supplier_bill (id TEXT PRIMARY KEY, supplier_id TEXT NULL, branch_id TEXT NULL, series TEXT NULL, number TEXT NULL, currency TEXT NULL, due_date TEXT NULL, balance_due NUMERIC NULL, status TEXT NULL)',
        'CREATE TABLE supplier_payment (id INTEGER PRIMARY KEY, supplier_bill_id TEXT NOT NULL, payment_method_id INTEGER NOT NULL, amount NUMERIC NULL, paid_at TEXT NULL, reference TEXT NULL, reversed_at TEXT NULL)',
        'CREATE TABLE bank_reconciliation_match (id INTEGER PRIMARY KEY AUTOINCREMENT, treasury_movement_id TEXT NULL UNIQUE)',
        'CREATE TABLE cash_movement_category (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, type TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1)',
        "INSERT INTO cash_movement_category (code, name, type) VALUES ('supplier_payment', 'Pago a proveedor', 'expense'), ('supplier_payment_reversal', 'Reversión', 'income'), ('payroll_payment', 'Pago de planilla', 'expense'), ('treasury_transfer_out', 'Transferencia a otra cuenta', 'expense'), ('treasury_transfer_in', 'Transferencia desde otra cuenta', 'income')",
        'CREATE TABLE cash_movements (
            id INTEGER PRIMARY KEY AUTOINCREMENT, cash_session_id INTEGER NOT NULL, movement_type TEXT NOT NULL, category_id INTEGER NULL,
            payment_method_id INTEGER NULL, amount NUMERIC NOT NULL, description TEXT NULL, occurred_at TEXT NULL, created_by INTEGER NULL,
            idempotency_key TEXT NULL UNIQUE, created_at TEXT NULL)',
    ];
}
