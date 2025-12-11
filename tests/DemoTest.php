<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;

class DemoTest extends TestCase
{
    public function testDemo(): void
    {
        self::assertDirectoryExists(__DIR__ . '/../src');
    }
}
