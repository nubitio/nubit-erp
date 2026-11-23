<?php

declare(strict_types=1);

namespace App\Tests\Identity\Auth;

use App\Identity\Auth\MercureAudience;
use App\Identity\Auth\MercureJsonResponseCookieListener;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Nubit\AdminBundle\Auth\CookieFactory;
use Nubit\AdminBundle\Auth\MercureSubscriberTokenService;
use Nubit\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class MercureJsonResponseCookieListenerTest extends TestCase
{
    public function testJsonLoginResponseGetsMercureCookie(): void
    {
        $tenantContext = new TenantContext();
        $tenantContext->setTenant(1, 'tenant', 'tenant.example.test', 'request-id');
        $listener = new MercureJsonResponseCookieListener(
            new MercureSubscriberTokenService('test-mercure-secret-32-characters!', 3600),
            new CookieFactory(false),
            $tenantContext,
            new MercureAudience(),
        );
        $request = Request::create('http://tenant.example.test/api/auth/login', 'POST');
        $request->attributes->set('_route', 'nubit_admin_auth_login');
        $response = new JsonResponse(['token' => bin2hex(random_bytes(8)), 'expiresAt' => time() + 3600]);

        $listener(new ResponseEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertSame('mercureAuthorization', $cookies[0]->getName());
        self::assertSame('/.well-known/mercure', $cookies[0]->getPath());
        self::assertNotEmpty($cookies[0]->getValue());
    }

    /** A pinned audience is what the hub was told to expect, whatever scheme the request is seen with. */
    public function testThePinnedAudienceIsSignedIntoTheCookie(): void
    {
        $signingKey = hash(algo: 'sha256', data: 'Mercure listener test signing key');
        $tenantContext = new TenantContext();
        $tenantContext->setTenant(1, 'tenant', 'tenant.example.test', 'request-id');
        $listener = new MercureJsonResponseCookieListener(
            new MercureSubscriberTokenService($signingKey, 3600),
            new CookieFactory(false),
            $tenantContext,
            new MercureAudience('https://nubit.pe/.well-known/mercure'),
        );
        $request = Request::create('http://tenant.example.test/api/auth/login', 'POST');
        $request->attributes->set('_route', 'nubit_admin_auth_login');
        $response = new JsonResponse(['token' => bin2hex(random_bytes(8)), 'expiresAt' => time() + 3600]);

        $listener(new ResponseEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $claims = (array) JWT::decode(
            (string) $response->headers->getCookies()[0]->getValue(),
            new Key($signingKey, 'HS256'),
        );
        self::assertSame('https://nubit.pe/.well-known/mercure', $claims['aud']);
    }

    public function testNonAuthJsonResponseIsUntouched(): void
    {
        $listener = new MercureJsonResponseCookieListener(
            new MercureSubscriberTokenService('test-mercure-secret-32-characters!', 3600),
            new CookieFactory(false),
            new TenantContext(),
            new MercureAudience(),
        );
        $request = Request::create('http://tenant.example.test/api/products');
        $request->attributes->set('_route', 'api_products_get_collection');
        $response = new JsonResponse(['items' => []]);

        $listener(new ResponseEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        self::assertSame([], $response->headers->getCookies());
    }

    private function kernel(): HttpKernelInterface
    {
        return $this->createStub(HttpKernelInterface::class);
    }
}
