<?php

declare(strict_types=1);

namespace App\Tests\Identity\Auth;

use App\Identity\Auth\MercureSubscriberTokenService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;

final class MercureSubscriberTokenServiceTest extends TestCase
{
    private const string SECRET = 'test-mercure-secret-at-least-32-chars-long!!';
    private const int TTL = 3600;

    private MercureSubscriberTokenService $service;

    protected function setUp(): void
    {
        $this->service = new MercureSubscriberTokenService(self::SECRET, self::TTL);
    }

    public function testGeneratesValidJwtSignedWithMercureSecret(): void
    {
        $token = $this->service->generateSubscriberToken('https://tenant.efact.app');

        $payload = (array) JWT::decode($token, new Key(self::SECRET, 'HS256'));

        self::assertArrayHasKey('mercure', $payload);
        self::assertArrayHasKey('subscribe', (array) $payload['mercure']);
    }

    public function testSubscribeClaimContainsTenantApiWildcard(): void
    {
        $token = $this->service->generateSubscriberToken('https://tenant.efact.app');

        $payload = (array) JWT::decode($token, new Key(self::SECRET, 'HS256'));
        $subscribe = (array) ((array) $payload['mercure'])['subscribe'];

        self::assertContains('https://tenant.efact.app/api/*', $subscribe);
    }

    public function testTokenExpiresWithinConfiguredTtl(): void
    {
        $before = time();
        $token = $this->service->generateSubscriberToken('https://tenant.efact.app');
        $after = time();

        $payload = (array) JWT::decode($token, new Key(self::SECRET, 'HS256'));

        self::assertArrayHasKey('exp', $payload);
        self::assertGreaterThanOrEqual($before + self::TTL, $payload['exp']);
        self::assertLessThanOrEqual($after + self::TTL, $payload['exp']);
    }

    public function testDifferentTenantsGetDifferentSubscribeClaims(): void
    {
        $tokenA = $this->service->generateSubscriberToken('https://tenant1.efact.app');
        $tokenB = $this->service->generateSubscriberToken('https://tenant2.efact.app');

        $payloadA = (array) JWT::decode($tokenA, new Key(self::SECRET, 'HS256'));
        $payloadB = (array) JWT::decode($tokenB, new Key(self::SECRET, 'HS256'));

        $subscribeA = (array) ((array) $payloadA['mercure'])['subscribe'];
        $subscribeB = (array) ((array) $payloadB['mercure'])['subscribe'];

        self::assertContains('https://tenant1.efact.app/api/*', $subscribeA);
        self::assertContains('https://tenant2.efact.app/api/*', $subscribeB);
        self::assertNotSame($subscribeA, $subscribeB);
    }

    public function testEmptyOriginGeneratesTokenWithEmptyPrefix(): void
    {
        $token = $this->service->generateSubscriberToken('');

        $payload = (array) JWT::decode($token, new Key(self::SECRET, 'HS256'));
        $subscribe = (array) ((array) $payload['mercure'])['subscribe'];

        self::assertContains('/api/*', $subscribe);
    }
}
