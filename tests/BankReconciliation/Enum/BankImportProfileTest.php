<?php

declare(strict_types=1);

namespace App\Tests\BankReconciliation\Enum;

use App\BankReconciliation\Enum\BankImportProfile;
use PHPUnit\Framework\TestCase;

final class BankImportProfileTest extends TestCase
{
    public function testBcpUsesDebitCreditColumns(): void
    {
        $map = BankImportProfile::BCP->columnMap();

        self::assertSame('debit_credit', $map['amountMode']);
        self::assertSame('D', $map['debitColumn']);
        self::assertSame('E', $map['creditColumn']);
        self::assertNull($map['amountColumn']);
    }

    public function testBbvaUsesSingleSignedColumn(): void
    {
        $map = BankImportProfile::BBVA->columnMap();

        self::assertSame('single', $map['amountMode']);
        self::assertSame('C', $map['amountColumn']);
        self::assertNull($map['debitColumn']);
        self::assertNull($map['creditColumn']);
    }

    public function testGenericFallbackHasSensibleDefaults(): void
    {
        $map = BankImportProfile::GENERIC->columnMap();

        self::assertSame(1, $map['headerRow']);
        self::assertSame('single', $map['amountMode']);
        self::assertSame('d/m/Y', $map['dateFormat']);
    }

    public function testLabelsListsAllCases(): void
    {
        self::assertSame(['BCP', 'BBVA', 'INTERBANK', 'GENERIC'], BankImportProfile::labels());
    }

    public function testTryFromRejectsUnknownBank(): void
    {
        $unknownBank = 'UNKNOWN_BANK' . random_int(1, 9999);

        self::assertNull(BankImportProfile::tryFrom($unknownBank));
    }
}
