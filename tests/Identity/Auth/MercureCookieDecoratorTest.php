<?php

declare(strict_types=1);

namespace App\Tests\Identity\Auth;

use App\Identity\Auth\MercureAudience;
use App\Identity\Auth\MercureCookieDecorator;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Nubit\AdminBundle\Auth\CookieFactory;
use Nubit\AdminBundle\Auth\MercureSubscriberTokenService;
use Nubit\AdminBundle\Auth\TokenPair;
use Nubit\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

final class MercureCookieDecoratorTest extends TestCase
{
    public function testCookieContainsHubAudienceAndTenantScopedSubscription(): void
    {
        $signingKey = hash(algo: 'sha256', data: 'Mercure cookie decorator test signing key');
        $issuer = 'https://mercure.example.test';
        $tenantContext = new TenantContext();
        $tenantContext->setTenant(1, 'demo', 'demo.example.test', 'request-1');
        $decorator = new MercureCookieDecorator(
            new MercureSubscriberTokenService($signingKey, 900, $issuer),
            new CookieFactory(cookieSecure: false),
            $tenantContext,
            new MercureAudience(),
        );
        $response = new JsonResponse();
        $request = Request::create('https://demo.example.test/login');

        $decorator->decorate(
            $response,
            $this->createStub(UserInterface::class),
            new TokenPair('access', 'refresh', time() + 300, time() + 3600, []),
            $request,
        );

        $cookies = $response->headers->getCookies();
        static::assertCount(1, $cookies);
        static::assertSame(MercureCookieDecorator::MERCURE_COOKIE, $cookies[0]->getName());
        $token = $cookies[0]->getValue();
        static::assertNotNull($token);

        $claims = json_encode(
            JWT::decode($token, new Key($signingKey, 'HS256')),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );

        static::assertStringContainsString('"iss":"' . $issuer . '"', $claims);
        static::assertStringContainsString('"aud":"https://demo.example.test/.well-known/mercure"', $claims);
        static::assertStringContainsString('"match":"https://demo.example.test/api/*"', $claims);
        static::assertStringContainsString('"match_type":"urlpattern"', $claims);
    }

    /**
     * Behind a proxy that terminates TLS the request can be seen as plain HTTP while the hub, or the browser, is on
     * HTTPS: the token must carry the audience the hub was pinned to, not the one the request suggests.
     */
    public function testThePinnedAudienceReplacesTheOneTheRequestSuggests(): void
    {
        $signingKey = hash(algo: 'sha256', data: 'Mercure cookie decorator test signing key');
        $tenantContext = new TenantContext();
        $tenantContext->setTenant(1, 'demo', 'demo.example.test', 'request-1');
        $decorator = new MercureCookieDecorator(
            new MercureSubscriberTokenService($signingKey, 900, 'https://mercure.example.test'),
            new CookieFactory(cookieSecure: false),
            $tenantContext,
            new MercureAudience('https://nubit.pe/.well-known/mercure'),
        );
        $response = new JsonResponse();

        $decorator->decorate(
            $response,
            $this->createStub(UserInterface::class),
            new TokenPair('access', 'refresh', time() + 300, time() + 3600, []),
            Request::create('http://demo.example.test/login'),
        );

        $claims = (array) JWT::decode(
            (string) $response->headers->getCookies()[0]->getValue(),
            new Key($signingKey, 'HS256'),
        );
        static::assertSame('https://nubit.pe/.well-known/mercure', $claims['aud']);
    }
}
