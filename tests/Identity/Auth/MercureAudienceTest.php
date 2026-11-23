<?php

declare(strict_types=1);

namespace App\Tests\Identity\Auth;

use App\Identity\Auth\MercureAudience;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class MercureAudienceTest extends TestCase
{
    public function testWithoutAPinnedValueItIsTheHubOfTheRequestsOwnOrigin(): void
    {
        $request = Request::create('https://tenant1.efact.app/api/auth/login');

        static::assertSame(
            'https://tenant1.efact.app/.well-known/mercure',
            new MercureAudience()->forRequest($request),
        );
    }

    #[DataProvider('unpinned')]
    public function testABlankPinnedValueIsNotAPin(?string $pinned): void
    {
        $request = Request::create('http://localhost:8080/api/auth/login');

        static::assertSame(
            'http://localhost:8080/.well-known/mercure',
            new MercureAudience($pinned)->forRequest($request),
        );
    }

    /** @return iterable<string, array{?string}> */
    public static function unpinned(): iterable
    {
        yield 'unset' => [null];
        yield 'empty' => [''];
        yield 'blank' => ['   '];
    }

    /**
     * Whatever scheme or host the request is seen with behind a proxy, the token carries the one audience the hub
     * was told to expect.
     */
    public function testAPinnedAudienceDoesNotDependOnWhatTheRequestLooksLike(): void
    {
        $audience = new MercureAudience('https://nubit.pe/.well-known/mercure');

        static::assertSame(
            'https://nubit.pe/.well-known/mercure',
            $audience->forRequest(Request::create('http://tenant1.efact.app/api/auth/login')),
        );
        static::assertSame(
            'https://nubit.pe/.well-known/mercure',
            $audience->forRequest(Request::create('https://tenant2.efact.app/api/auth/refresh')),
        );
    }
}
