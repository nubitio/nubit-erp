<?php

declare(strict_types=1);

namespace App\Tests\Efact\Web;

use App\Efact\Web\CspNonceService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CspNonceServiceTest extends TestCase
{
    private function makeStack(?Request $request = null): RequestStack
    {
        $stack = new RequestStack();
        if ($request !== null) {
            $stack->push($request);
        }

        return $stack;
    }

    public function testGetNonceReturnsSameValueOnMultipleCalls(): void
    {
        $service = new CspNonceService($this->makeStack(Request::create('/')));

        $nonce1 = $service->getNonce();
        $nonce2 = $service->getNonce();

        self::assertSame($nonce1, $nonce2);
    }

    public function testGetNonceIsBase64Encoded(): void
    {
        $service = new CspNonceService($this->makeStack(Request::create('/')));

        $nonce = $service->getNonce();

        // base64url-safe string (without trailing =)
        self::assertMatchesRegularExpression('/^[A-Za-z0-9+\/]+$/', $nonce);
    }

    public function testGetNonceHasReasonableLength(): void
    {
        $service = new CspNonceService($this->makeStack(Request::create('/')));

        $nonce = $service->getNonce();

        // 16 bytes → ~22 base64 chars (without padding)
        self::assertGreaterThanOrEqual(16, strlen($nonce));
    }

    public function testGetNonceWorksWithNoCurrentRequest(): void
    {
        // When there is no request, key defaults to 0 — should not throw
        $service = new CspNonceService($this->makeStack(null));

        $nonce = $service->getNonce();

        self::assertNotEmpty($nonce);
    }

    public function testDifferentRequestsGetDifferentNonces(): void
    {
        $req1 = Request::create('/page1');
        $req2 = Request::create('/page2');

        $stack = new RequestStack();

        $stack->push($req1);
        $service = new CspNonceService($stack);
        $nonce1 = $service->getNonce();
        $stack->pop();

        $stack->push($req2);
        $nonce2 = $service->getNonce();
        $stack->pop();

        // Different request objects → different nonces (different spl_object_id keys)
        self::assertNotSame($nonce1, $nonce2);
    }
}
