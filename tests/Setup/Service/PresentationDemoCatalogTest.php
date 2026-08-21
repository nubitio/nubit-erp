<?php

declare(strict_types=1);

namespace App\Tests\Setup\Service;

use App\Setup\Service\PresentationDemoCatalog;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class PresentationDemoCatalogTest extends TestCase
{
    public function testProductCodesAreUniqueAndPresentationReady(): void
    {
        $codes = array_column(PresentationDemoCatalog::products(), 'code');
        $names = array_column(PresentationDemoCatalog::products(), 'name');

        self::assertSame($codes, array_unique($codes));
        self::assertNotEmpty($codes);
        foreach ($names as $name) {
            self::assertDoesNotMatchRegularExpression('/#\d+|Test /', $name);
        }
        foreach ($codes as $code) {
            self::assertDoesNotMatchRegularExpression('/^PROD-/', $code);
        }
    }

    public function testCustomersUseNamedBusinessesAndPeople(): void
    {
        $customers = PresentationDemoCatalog::customers();
        self::assertGreaterThanOrEqual(8, count($customers));
        foreach ($customers as $customer) {
            self::assertMatchesRegularExpression('/^\d{8,11}$/', $customer['ruc']);
            self::assertStringContainsString('@', $customer['email']);
            self::assertDoesNotMatchRegularExpression('/cliente\d+@test\.com/', $customer['email']);
        }
    }

    public function testSaleMomentsCoverTodayYesterdayWeekAndYear(): void
    {
        $now = new DateTimeImmutable('2026-08-21 12:00:00', new DateTimeZone('America/Lima'));
        $moments = PresentationDemoCatalog::saleMoments($now);

        $days = [];
        $months = [];
        foreach ($moments as $moment) {
            $lima = $moment->setTimezone(new DateTimeZone('America/Lima'));
            $days[$lima->format('Y-m-d')] = true;
            $months[$lima->format('Y-m')] = true;
        }

        self::assertArrayHasKey('2026-08-21', $days);
        self::assertArrayHasKey('2026-08-20', $days);
        self::assertGreaterThanOrEqual(6, count($days));
        self::assertGreaterThanOrEqual(12, count($months));
        self::assertGreaterThanOrEqual(50, count($moments));
    }

    public function testPayrollAndFinanceCatalogIsPopulated(): void
    {
        self::assertCount(6, PresentationDemoCatalog::employees());
        self::assertCount(4, PresentationDemoCatalog::afpRates());
        self::assertCount(2, PresentationDemoCatalog::bankAccounts());
        self::assertCount(3, PresentationDemoCatalog::fixedAssets());
        self::assertContains('Credito', PresentationDemoCatalog::paymentMethodRotation());
    }
}
