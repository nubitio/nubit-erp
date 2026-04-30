<?php

declare(strict_types=1);

namespace App\Tests\Identity\Auth;

use App\Identity\Auth\JWTManager;
use Firebase\JWT\ExpiredException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use UnexpectedValueException;

final class JWTManagerTest extends TestCase
{
    private const string SECRET = 'test-secret-key-for-unit-tests-at-least-32-chars';

    private JWTManager $manager;

    protected function setUp(): void
    {
        $this->manager = new JWTManager(self::SECRET, new NullLogger());
    }

    // ------------------------------------------------------------------ encode / decode round-trip

    public function testEncodeDecodeRoundTripPreservesPayload(): void
    {
        $payload = [
            'sub'   => 'user-42',
            'roles' => ['ROLE_USER', 'ROLE_ADMIN'],
            'exp'   => time() + 3600,
        ];

        $token   = $this->manager->encode($payload);
        $decoded = $this->manager->decode($token);

        self::assertSame('user-42', $decoded['sub']);
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], (array) $decoded['roles']);
        self::assertArrayHasKey('exp', $decoded);
    }

    public function testEncodeDecodeRoundTripWithCustomExpiresIn(): void
    {
        $before = time();
        $payload = ['sub' => 'user-1'];

        $token   = $this->manager->encode($payload, expiresIn: 7200);
        $decoded = $this->manager->decode($token);

        self::assertArrayHasKey('exp', $decoded);
        // exp should be between now+7200 and now+7200+5 (test clock slack)
        self::assertGreaterThanOrEqual($before + 7200, $decoded['exp']);
        self::assertLessThanOrEqual($before + 7205, $decoded['exp']);
    }

    public function testCustomExpiresInOverridesAnyExistingExpInPayload(): void
    {
        $before = time();
        // payload has its own exp; expiresIn should overwrite it
        $payload = ['sub' => 'u', 'exp' => time() + 100];

        $token   = $this->manager->encode($payload, expiresIn: 3600);
        $decoded = $this->manager->decode($token);

        self::assertGreaterThanOrEqual($before + 3600, $decoded['exp']);
    }

    public function testEncodeWithoutExpiresInDoesNotInjectExp(): void
    {
        $payload = ['sub' => 'no-exp'];

        $token   = $this->manager->encode($payload);
        $decoded = $this->manager->decode($token);

        self::assertArrayNotHasKey('exp', $decoded);
    }

    // ------------------------------------------------------------------ verify

    public function testVerifyReturnsTrueForValidToken(): void
    {
        $token = $this->manager->encode(['sub' => 'ok', 'exp' => time() + 3600]);

        self::assertTrue($this->manager->verify($token));
    }

    public function testVerifyReturnsFalseForTamperedToken(): void
    {
        $token = $this->manager->encode(['sub' => 'ok', 'exp' => time() + 3600]);

        // Tamper with the payload segment (second part) — this guarantees the
        // decoded bytes differ and the HMAC signature verification fails.
        // (Flipping the last character of the *signature* is unreliable because
        // base64url trailing-padding bits are ignored during decoding.)
        $parts = explode('.', $token);
        $parts[1] = substr($parts[1], 0, -1) . ($parts[1][-1] === 'A' ? 'B' : 'A');
        $tampered = implode('.', $parts);

        self::assertFalse($this->manager->verify($tampered));
    }

    public function testVerifyReturnsFalseForTokenSignedWithDifferentSecret(): void
    {
        $other = new JWTManager('completely-different-secret-at-least-32-chars!!', new NullLogger());
        $token = $other->encode(['sub' => 'evil', 'exp' => time() + 3600]);

        self::assertFalse($this->manager->verify($token));
    }

    public function testVerifyReturnsFalseForGarbageInput(): void
    {
        self::assertFalse($this->manager->verify('not.a.jwt'));
    }

    public function testVerifyLogsErrorOnInvalidToken(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with('Invalid JWT token', self::arrayHasKey('exception'));

        $mgr = new JWTManager(self::SECRET, $logger);
        $mgr->verify('garbage.token.value');
    }

    // ------------------------------------------------------------------ isExpired

    public function testIsExpiredReturnsFalseForFutureToken(): void
    {
        $token = $this->manager->encode(['sub' => 'u', 'exp' => time() + 3600]);

        self::assertFalse($this->manager->isExpired($token));
    }

    public function testIsExpiredReturnsTrueForExpiredToken(): void
    {
        // Build a token manually with an exp already in the past.
        // We create it with Firebase JWT directly to bypass the leeway check.
        $pastPayload = ['sub' => 'u', 'exp' => time() - 100];

        // Firebase JWT decode enforces expiry, so we need to test via a token
        // signed with a very old exp. We can verify the logic by checking the
        // decode path throws ExpiredException, which isExpired() catches → true.
        $token = \Firebase\JWT\JWT::encode($pastPayload, self::SECRET, 'HS256');

        self::assertTrue($this->manager->isExpired($token));
    }

    public function testIsExpiredReturnsFalseWhenNoExpClaim(): void
    {
        // Token without exp should never be considered expired
        $token = $this->manager->encode(['sub' => 'u']);

        self::assertFalse($this->manager->isExpired($token));
    }

    public function testIsExpiredReturnsTrueForGarbageToken(): void
    {
        // Unparseable token → decode throws → treat as expired
        self::assertTrue($this->manager->isExpired('not.valid.jwt'));
    }

    public function testIsExpiredLogsErrorForInvalidToken(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with('Invalid JWT token', self::arrayHasKey('exception'));

        $mgr = new JWTManager(self::SECRET, $logger);
        $mgr->isExpired('bad.token.here');
    }

    // ------------------------------------------------------------------ decode exception behaviour

    public function testDecodeThrowsForMalformedToken(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->manager->decode('not-a-jwt-at-all');
    }

    public function testDecodeThrowsForWrongSegmentCount(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->manager->decode('only.two');
    }

    public function testDecodeThrowsForExpiredToken(): void
    {
        $token = \Firebase\JWT\JWT::encode(['sub' => 'u', 'exp' => time() - 100], self::SECRET, 'HS256');

        $this->expectException(ExpiredException::class);
        $this->manager->decode($token);
    }

    // ------------------------------------------------------------------ constructor guard

    public function testConstructorThrowsForEmptySecret(): void
    {
        $this->expectException(LogicException::class);
        new JWTManager('', new NullLogger());
    }

    public function testConstructorThrowsForWhitespaceOnlySecret(): void
    {
        $this->expectException(LogicException::class);
        new JWTManager('   ', new NullLogger());
    }

    // ------------------------------------------------------------------ ping

    public function testPingIsNoOp(): void
    {
        // Should not throw or return anything meaningful
        $this->manager->ping();
        $this->addToAssertionCount(1); // explicit assertion to satisfy strict mode
    }
}
