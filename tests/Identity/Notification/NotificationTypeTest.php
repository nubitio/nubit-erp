<?php

declare(strict_types=1);

namespace App\Tests\Identity\Notification;

use App\Identity\Notification\NotificationType;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NotificationType::class)]
final class NotificationTypeTest extends TestCase
{
    public function testSunatErrorConstant(): void
    {
        self::assertSame('sunat_error', NotificationType::SUNAT_ERROR);
    }

    public function testSunatBlockedConstant(): void
    {
        self::assertSame('sunat_blocked', NotificationType::SUNAT_BLOCKED);
    }

    public function testStockLowConstant(): void
    {
        self::assertSame('stock_low', NotificationType::STOCK_LOW);
    }

    public function testCashNotOpenedConstant(): void
    {
        self::assertSame('cash_not_opened', NotificationType::CASH_NOT_OPENED);
    }

    public function testQuotaWarningConstant(): void
    {
        self::assertSame('quota_warning', NotificationType::QUOTA_WARNING);
    }

    public function testDailyBriefingConstant(): void
    {
        self::assertSame('daily_briefing', NotificationType::DAILY_BRIEFING);
    }

    public function testCertificateExpiringConstant(): void
    {
        self::assertSame('certificate_expiring', NotificationType::CERTIFICATE_EXPIRING);
    }

    public function testAllValuesAreUnique(): void
    {
        $values = [
            NotificationType::SUNAT_ERROR,
            NotificationType::SUNAT_BLOCKED,
            NotificationType::STOCK_LOW,
            NotificationType::CASH_NOT_OPENED,
            NotificationType::QUOTA_WARNING,
            NotificationType::DAILY_BRIEFING,
            NotificationType::CERTIFICATE_EXPIRING,
        ];

        self::assertSame(count($values), count(array_unique($values)));
    }

    public function testAllValuesAreLowercaseStrings(): void
    {
        $values = [
            NotificationType::SUNAT_ERROR,
            NotificationType::SUNAT_BLOCKED,
            NotificationType::STOCK_LOW,
            NotificationType::CASH_NOT_OPENED,
            NotificationType::QUOTA_WARNING,
            NotificationType::DAILY_BRIEFING,
            NotificationType::CERTIFICATE_EXPIRING,
        ];

        foreach ($values as $value) {
            self::assertSame(strtolower($value), $value, "Value '$value' is not lowercase");
        }
    }
}
