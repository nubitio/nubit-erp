<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Integration\Entity\ApiClient;
use App\Integration\Entity\ApiKey;
use App\Integration\Security\ApiClientUser;
use PHPUnit\Framework\TestCase;

final class ApiClientAndKeyTest extends TestCase
{
    public function testScopesBecomeRoles(): void
    {
        $user = new ApiClientUser('c1', 'nubit-control', 'nubit', ['documents:write', 'documents:read']);

        self::assertSame(
            ['ROLE_INTEGRATION', 'ROLE_SCOPE_DOCUMENTS_WRITE', 'ROLE_SCOPE_DOCUMENTS_READ'],
            $user->getRoles(),
        );
        self::assertSame('api-client:c1', $user->getUserIdentifier());
        self::assertSame('nubit', $user->getTenant());
    }

    public function testRoleForScopeNormalisesSeparators(): void
    {
        self::assertSame('ROLE_SCOPE_WEBHOOKS_MANAGE', ApiClientUser::roleForScope('webhooks:manage'));
        self::assertSame('ROLE_SCOPE_DOCUMENT_PDF_READY', ApiClientUser::roleForScope('document.pdf-ready'));
    }

    public function testClientDeduplicatesScopes(): void
    {
        $client = new ApiClient('x', ['documents:read', 'documents:read', 'documents:write']);

        self::assertSame(['documents:read', 'documents:write'], $client->getScopes());
        self::assertTrue($client->hasScope('documents:write'));
        self::assertFalse($client->hasScope('webhooks:manage'));
    }

    public function testNewKeyIsLinkedBothWays(): void
    {
        $client = new ApiClient('x', ['documents:read']);
        $key = new ApiKey($client, 'nb_live_nubit_abcd1234', str_repeat('0', 64));

        self::assertCount(1, $client->getKeys());
        self::assertSame($client, $key->getClient());
        self::assertFalse($key->isRevoked());
    }

    public function testTouchWritesOnlyWhenStale(): void
    {
        $client = new ApiClient('x', ['documents:read']);
        $key = new ApiKey($client, 'nb_live_nubit_abcd1234', str_repeat('0', 64));

        $t0 = new \DateTimeImmutable('2026-01-01T00:00:00Z');
        self::assertTrue($key->touch($t0), 'first touch always writes');
        self::assertFalse($key->touch($t0->modify('+30 seconds')), 'within granularity: no write');
        self::assertTrue($key->touch($t0->modify('+61 seconds')), 'past granularity: writes again');
    }

    public function testRevokeIsIdempotent(): void
    {
        $client = new ApiClient('x', ['documents:read']);
        $key = new ApiKey($client, 'nb_live_nubit_abcd1234', str_repeat('0', 64));

        $first = new \DateTimeImmutable('2026-01-01T00:00:00Z');
        $key->revoke($first);
        $key->revoke(new \DateTimeImmutable('2026-02-01T00:00:00Z'));

        self::assertTrue($key->isRevoked());
        self::assertEquals($first, $key->getRevokedAt());
    }
}
