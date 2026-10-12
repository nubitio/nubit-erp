<?php

declare(strict_types=1);

namespace App\Tests\Identity\Auth;

use App\Identity\Auth\SessionCredentialRequestListener;
use Nubit\AdminBundle\Auth\CsrfProtectionListener;
use Nubit\AdminBundle\Auth\CsrfTokenPolicy;
use Nubit\AdminBundle\Auth\JWTAuthenticator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SessionCredentialRequestListenerTest extends TestCase
{
    public function testBearerCannotBypassCsrfForCookieRefresh(): void
    {
        $request = Request::create('/api/auth/refresh', method: 'POST');
        $request->attributes->set('_route', 'nubit_admin_auth_refresh');
        $request->cookies->set(JWTAuthenticator::REFRESH_COOKIE, bin2hex(random_bytes(32)));
        $request->cookies->set(CsrfTokenPolicy::COOKIE_NAME, CsrfTokenPolicy::generate());
        $request->headers->set('Authorization', 'Bearer ' . bin2hex(random_bytes(32)));
        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
        (new SessionCredentialRequestListener())($event);
        (new CsrfProtectionListener(new NullLogger()))($event);
        static::assertSame(403, $event->getResponse()?->getStatusCode());
        static::assertFalse($request->headers->has('Authorization'));
    }

    public function testCookieRefreshRetainsDoubleSubmitProtection(): void
    {
        $request = Request::create('/api/auth/refresh', method: 'POST');
        $request->attributes->set('_route', 'nubit_admin_auth_refresh');
        $csrf = CsrfTokenPolicy::generate();
        $request->cookies->set(JWTAuthenticator::REFRESH_COOKIE, bin2hex(random_bytes(32)));
        $request->cookies->set(CsrfTokenPolicy::COOKIE_NAME, $csrf);
        $request->headers->set(CsrfTokenPolicy::HEADER_NAME, $csrf);
        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
        (new SessionCredentialRequestListener())($event);
        (new CsrfProtectionListener(new NullLogger()))($event);
        static::assertFalse($event->hasResponse());
        static::assertTrue($request->cookies->has(JWTAuthenticator::REFRESH_COOKIE));
    }
}
