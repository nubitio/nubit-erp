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
}
