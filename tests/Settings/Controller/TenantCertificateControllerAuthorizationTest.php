<?php

declare(strict_types=1);

namespace App\Tests\Settings\Controller;

use App\Settings\Controller\TenantCertificateController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TenantCertificateControllerAuthorizationTest extends TestCase
{
    /**
     * Certificate material is an administrator-only settings capability. The
     * controller deliberately does not use the broad ROLE_USER gate.
     */
    public function testCertificateLifecycleRequiresSettingsEdit(): void
    {
        $reflection = new \ReflectionClass(TenantCertificateController::class);

        static::assertSame([], $reflection->getAttributes(IsGranted::class));
        static::assertSame(
            ["perm:settings.edit"],
            $this->grantsFor($reflection->getMethod('__invoke')),
        );
        static::assertSame(
            ["perm:settings.edit"],
            $this->grantsFor($reflection->getMethod('get')),
        );
    }

    /**
     * @return list<string>
     */
    private function grantsFor(\ReflectionMethod $method): array
    {
        return array_map(
            static fn (IsGranted $attribute): string => $attribute->attribute,
            $method->getAttributes(IsGranted::class, \ReflectionAttribute::IS_INSTANCEOF),
        );
    }
}
