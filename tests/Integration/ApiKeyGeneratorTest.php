<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Integration\Security\ApiKeyGenerator;
use PHPUnit\Framework\TestCase;

final class ApiKeyGeneratorTest extends TestCase
{
    private ApiKeyGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ApiKeyGenerator();
    }

    public function testGeneratesAWellFormedLiveKey(): void
    {
        $material = $this->generator->generate('nubit');

        self::assertStringStartsWith('nb_live_nubit_', $material->secret);
        self::assertSame('live', $material->environment);
        self::assertSame('nubit', $material->tenant);
        self::assertSame(64, strlen($material->tokenHash));
        self::assertSame(hash('sha256', $material->secret), $material->tokenHash);
    }

    public function testTestFlagChangesTheEnvironmentSegment(): void
    {
        $material = $this->generator->generate('nubit', test: true);

        self::assertStringStartsWith('nb_test_nubit_', $material->secret);
        self::assertSame('test', $material->environment);
    }

    public function testPrefixIsAStablePublicSelector(): void
    {
        $material = $this->generator->generate('nubit');
        $parsed = $this->generator->parse($material->secret);

        self::assertNotNull($parsed);
        self::assertSame($material->prefix, $parsed['prefix']);
        self::assertSame($material->tokenHash, $parsed['tokenHash']);
        self::assertSame('nubit', $parsed['tenant']);
        self::assertSame('nb_live_nubit_', substr($material->prefix, 0, 14));
        self::assertSame(14 + 8, strlen($material->prefix));
    }

    public function testTwoKeysNeverCollide(): void
    {
        $a = $this->generator->generate('nubit');
        $b = $this->generator->generate('nubit');

        self::assertNotSame($a->secret, $b->secret);
        self::assertNotSame($a->prefix, $b->prefix);
    }

    public function testParseRejectsGarbage(): void
    {
        self::assertNull($this->generator->parse('not-a-key'));
        self::assertNull($this->generator->parse('nb_live_nubit_short'));
        self::assertNull($this->generator->parse('nb_prod_nubit_' . str_repeat('a', 43)));
        self::assertNull($this->generator->parse('Bearer nb_live_nubit_' . str_repeat('a', 43)));
    }

    public function testGenerateRejectsAnInvalidTenantSlug(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->generator->generate('Not A Slug');
    }
}
