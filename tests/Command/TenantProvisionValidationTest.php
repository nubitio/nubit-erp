<?php

declare(strict_types=1);

namespace App\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TenantProvisionValidationTest extends TestCase
{
    private const string PATTERN = '/^[a-z0-9][a-z0-9_-]{1,61}[a-z0-9]$/i';

    #[DataProvider('validTenantNames')]
    public function testAcceptsValidTenantNames(string $name): void
    {
        $this->assertMatchesRegularExpression(self::PATTERN, $name);
    }

    #[DataProvider('invalidTenantNames')]
    public function testRejectsInvalidTenantNames(string $name): void
    {
        $this->assertDoesNotMatchRegularExpression(self::PATTERN, $name);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validTenantNames(): iterable
    {
        yield 'simple' => ['acme'];
        yield 'with-hyphen' => ['acme-corp'];
        yield 'with-underscore' => ['acme_corp'];
        yield 'numeric' => ['tenant123'];
        yield 'min-length' => ['abc'];
        yield 'max-length' => [str_repeat('a', 63)];
        yield 'mixed' => ['my-tenant_2024'];
        yield 'starts-with-number' => ['1acme'];
        yield 'all-numbers' => ['123'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTenantNames(): iterable
    {
        yield 'too-short' => ['ab'];
        yield 'single-char' => ['a'];
        yield 'empty' => [''];
        yield 'too-long' => [str_repeat('a', 64)];
        yield 'starts-with-hyphen' => ['-acme'];
        yield 'ends-with-hyphen' => ['acme-'];
        yield 'starts-with-underscore' => ['_acme'];
        yield 'ends-with-underscore' => ['acme_'];
        yield 'has-dot' => ['acme.corp'];
        yield 'has-space' => ['acme corp'];
        yield 'sql-injection-attempt' => ['acme; DROP DATABASE'];
        yield 'backtick-injection' => ['acme`--'];
        yield 'quote-injection' => ["acme'OR'1"];
        yield 'special-chars' => ['acme$corp'];
        yield 'path-traversal' => ['../etc/passwd'];
        yield 'unicode' => ['ácme'];
    }
}
