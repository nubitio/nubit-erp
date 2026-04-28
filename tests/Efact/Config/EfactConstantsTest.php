<?php

declare(strict_types=1);

namespace App\Tests\Efact\Config;

use App\Efact\Config\EfactConstants;
use PHPUnit\Framework\TestCase;

final class EfactConstantsTest extends TestCase
{
    public function testStoragePathConstants(): void
    {
        self::assertSame('invoices', EfactConstants::INVOICE_DIR);
        self::assertSame('images', EfactConstants::IMAGE_DIR);
        self::assertSame('backups', EfactConstants::BACKUP_DIR);
        self::assertSame('binlogs', EfactConstants::BINLOG_DIR);
    }

    public function testTaxSettingConstants(): void
    {
        self::assertSame('app_option_igv', EfactConstants::TAX);
        self::assertSame('app_option_rc', EfactConstants::CONSUMPTION_TAX);
    }

    public function testSunatSettingConstants(): void
    {
        self::assertSame('app_option_sunat_send_enabled', EfactConstants::SUNAT_SEND_ENABLED);
        self::assertSame('app_option_sunat_rejection_streak', EfactConstants::SUNAT_REJECTION_STREAK);
        self::assertSame('app_option_sunat_rejection_threshold', EfactConstants::SUNAT_REJECTION_THRESHOLD);
        self::assertSame('app_option_sunat_send_retry_max_attempts', EfactConstants::SUNAT_SEND_RETRY_MAX_ATTEMPTS);
        self::assertSame('app_option_sunat_send_retry_base_seconds', EfactConstants::SUNAT_SEND_RETRY_BASE_SECONDS);
        self::assertSame('app_option_sunat_send_retry_max_seconds', EfactConstants::SUNAT_SEND_RETRY_MAX_SECONDS);
    }

    public function testSalesSettingConstants(): void
    {
        self::assertSame('sales_v2_mode', EfactConstants::SALES_V2_MODE);
        self::assertSame('sales_default_currency', EfactConstants::SALES_DEFAULT_CURRENCY);
        self::assertSame('sales_default_usd_exchange_rate', EfactConstants::SALES_DEFAULT_USD_EXCHANGE_RATE);
        self::assertSame('sales_usd_exchange_rate_updated_at', EfactConstants::EXCHANGE_RATE_UPDATED_AT);
    }

    public function testPurchasesSettingConstants(): void
    {
        self::assertSame('purchases_v2_mode', EfactConstants::PURCHASES_V2_MODE);
        self::assertSame('app_option_purchases_v2_dual_write_enabled', EfactConstants::PURCHASES_V2_DUAL_WRITE_ENABLED);
        self::assertSame('app_option_purchases_v2_dual_write_strict', EfactConstants::PURCHASES_V2_DUAL_WRITE_STRICT);
    }

    public function testInventorySettingConstants(): void
    {
        self::assertSame('inventory_negative_stock_policy', EfactConstants::INVENTORY_NEGATIVE_STOCK_POLICY);
        self::assertSame('inventory_core_mode', EfactConstants::INVENTORY_CORE_MODE);
        self::assertSame('inventory_core_dual_write_enabled', EfactConstants::INVENTORY_CORE_DUAL_WRITE);
        self::assertSame('inventory_core_read_stock_items_enabled', EfactConstants::INVENTORY_CORE_READ_FROM_STOCK_ITEMS);
    }

    public function testAllConstantsAreNonEmptyStrings(): void
    {
        $reflection = new \ReflectionClass(EfactConstants::class);

        foreach ($reflection->getConstants() as $name => $value) {
            self::assertIsString($value, "Constant {$name} should be a string");
            self::assertNotEmpty($value, "Constant {$name} should not be empty");
        }
    }

    public function testConstantsAreUnique(): void
    {
        $reflection = new \ReflectionClass(EfactConstants::class);
        $values = array_values($reflection->getConstants());

        self::assertSame(
            count($values),
            count(array_unique($values)),
            'All EfactConstants values must be unique'
        );
    }
}
