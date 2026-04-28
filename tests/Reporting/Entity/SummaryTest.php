<?php

declare(strict_types=1);

namespace App\Tests\Reporting\Entity;

use App\Reporting\Entity\Summary;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SummaryTest extends TestCase
{
    public function testDefaultsAreNull(): void
    {
        $summary = new Summary();

        self::assertNull($summary->getId());
        self::assertNull($summary->getDate());
        self::assertNull($summary->getTicket());
        self::assertNull($summary->getCorrelative());
        self::assertNull($summary->getHash());
        self::assertNull($summary->getCode());
        self::assertNull($summary->getMsg());
        self::assertNull($summary->getCreatedAt());
        self::assertNull($summary->getUpdatedAt());
    }

    public function testSettersReturnFluentInterface(): void
    {
        $summary = new Summary();
        $date = new DateTimeImmutable('2024-01-15');

        $result = $summary
            ->setDate($date)
            ->setTicket('T-001')
            ->setCorrelative('B001-00001')
            ->setHash('abc123')
            ->setCode('0')
            ->setMsg('La Boleta número B001-00001, ha sido aceptada')
            ->setCreatedAt($date)
            ->setUpdatedAt($date);

        self::assertSame($summary, $result);
    }

    public function testGettersReturnSetValues(): void
    {
        $summary = new Summary();
        $date = new DateTimeImmutable('2024-06-01');
        $updatedAt = new DateTimeImmutable('2024-06-02');

        $summary
            ->setDate($date)
            ->setTicket('T-999')
            ->setCorrelative('F001-00042')
            ->setHash('deadbeef')
            ->setCode('1')
            ->setMsg('Aceptado con observaciones')
            ->setCreatedAt($date)
            ->setUpdatedAt($updatedAt);

        self::assertSame($date, $summary->getDate());
        self::assertSame('T-999', $summary->getTicket());
        self::assertSame('F001-00042', $summary->getCorrelative());
        self::assertSame('deadbeef', $summary->getHash());
        self::assertSame('1', $summary->getCode());
        self::assertSame('Aceptado con observaciones', $summary->getMsg());
        self::assertSame($date, $summary->getCreatedAt());
        self::assertSame($updatedAt, $summary->getUpdatedAt());
    }

    public function testNullableFieldsAcceptNull(): void
    {
        $summary = new Summary();
        $date = new DateTimeImmutable();

        $summary->setDate($date);
        $summary->setDate(null);
        $summary->setTicket(null);
        $summary->setCorrelative(null);
        $summary->setHash(null);
        $summary->setCode(null);
        $summary->setMsg(null);
        $summary->setCreatedAt(null);
        $summary->setUpdatedAt(null);

        self::assertNull($summary->getDate());
        self::assertNull($summary->getTicket());
        self::assertNull($summary->getCorrelative());
        self::assertNull($summary->getHash());
        self::assertNull($summary->getCode());
        self::assertNull($summary->getMsg());
        self::assertNull($summary->getCreatedAt());
        self::assertNull($summary->getUpdatedAt());
    }
}
