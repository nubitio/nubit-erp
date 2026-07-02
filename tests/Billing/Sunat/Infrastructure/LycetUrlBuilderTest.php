<?php

declare(strict_types=1);

namespace App\Tests\Billing\Sunat\Infrastructure;

use App\Billing\Sunat\Infrastructure\LycetUrlBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Concrete class exposing the trait's private methods as public for testing.
 */
final class LycetUrlBuilderHost
{
    use LycetUrlBuilder;

    public function __construct(
        private readonly string $apiBaseUrl,
        private readonly string $apiToken,
    ) {}

    /** @param array<string, string|int|null> $extraParams */
    public function publicUrl(string $path, array $extraParams = []): string
    {
        return $this->lycetUrl($path, $extraParams);
    }

    /** @param array<string, string|int|null> $extraParams */
    public function publicUrlSafe(string $path, array $extraParams = []): string
    {
        return $this->lycetUrlSafe($path, $extraParams);
    }

    public function publicMaskAndRethrow(\Throwable $e): never
    {
        $this->maskAndRethrow($e);
    }
}

final class LycetUrlBuilderTest extends TestCase
{
    private LycetUrlBuilderHost $host;

    protected function setUp(): void
    {
        $this->host = new LycetUrlBuilderHost(apiBaseUrl: 'https://lycet.example.com', apiToken: 'secret-token-xyz');
    }

    public function testLycetUrlBuildsCorrectUrl(): void
    {
        $url = $this->host->publicUrl('invoice/send');

        self::assertStringStartsWith('https://lycet.example.com/api/v1/invoice/send', $url);
        self::assertStringContainsString('token=secret-token-xyz', $url);
    }

    public function testLycetUrlAppendsExtraParams(): void
    {
        $url = $this->host->publicUrl('invoice/status', ['ruc' => '20123456789', 'tipo' => '01']);

        self::assertStringContainsString('ruc=20123456789', $url);
        self::assertStringContainsString('tipo=01', $url);
        self::assertStringContainsString('token=secret-token-xyz', $url);
    }

    public function testLycetUrlTrimsTrailingSlashFromBase(): void
    {
        $host = new LycetUrlBuilderHost('https://lycet.example.com/', 'tok');
        $url = $host->publicUrl('some/path');

        self::assertStringNotContainsString('//api', $url);
        self::assertStringContainsString('/api/v1/some/path', $url);
    }

    public function testLycetUrlSafeMasksToken(): void
    {
        $url = $this->host->publicUrlSafe('invoice/send');

        self::assertStringContainsString('token=***', $url);
        self::assertStringNotContainsString('secret-token-xyz', $url);
    }

    public function testLycetUrlSafeAppendsExtraParams(): void
    {
        $url = $this->host->publicUrlSafe('report', ['ruc' => '20123456789']);

        self::assertStringContainsString('ruc=20123456789', $url);
        self::assertStringContainsString('token=***', $url);
    }

    public function testMaskAndRethrowRethrowsOriginalWhenTokenNotInMessage(): void
    {
        $original = new \RuntimeException('Generic network error');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Generic network error');

        $this->host->publicMaskAndRethrow($original);
    }

    public function testMaskAndRethrowWrapsWhenTokenAppearsInMessage(): void
    {
        $original = new \RuntimeException('cURL error: token=secret-token-xyz is invalid');

        try {
            $this->host->publicMaskAndRethrow($original);
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('token=***', $e->getMessage());
            self::assertStringNotContainsString('secret-token-xyz', $e->getMessage());
            self::assertSame($original, $e->getPrevious());
        }
    }
}
