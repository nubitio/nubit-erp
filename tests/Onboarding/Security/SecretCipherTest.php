<?php

declare(strict_types=1);

namespace App\Tests\Onboarding\Security;

use App\ControlPlane\Onboarding\Security\SecretCipher;
use PHPUnit\Framework\TestCase;

final class SecretCipherTest extends TestCase
{
    public function testEncryptsAndDecryptsSecretWithoutPlaintextEnvelope(): void
    {
        $cipher = new SecretCipher('unit-test-secret');

        $protected = $cipher->encrypt('Admin.3000');

        self::assertStringStartsWith('enc:v1:', $protected);
        self::assertStringNotContainsString('Admin.3000', $protected);
        self::assertSame('Admin.3000', $cipher->decrypt($protected));
    }

    public function testDecryptKeepsLegacyPlaintextCompatible(): void
    {
        self::assertSame('legacy-secret', (new SecretCipher('unit-test-secret'))->decrypt('legacy-secret'));
    }
}
