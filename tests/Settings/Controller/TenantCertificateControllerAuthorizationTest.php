<?php

declare(strict_types=1);

namespace App\Tests\Settings\Controller;

use App\ControlPlane\Certificate\CertificateStatus;
use App\ControlPlane\Certificate\TenantCertificate;
use App\Settings\Controller\TenantCertificateController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TenantCertificateControllerAuthorizationTest extends TestCase
{
    /**
     * Certificate material is an administrator-only settings capability. The
     * controller deliberately does not use the broad ROLE_USER gate.
     */
    public function testCertificateLifecycleUsesSeparateSettingsPermissions(): void
    {
        $reflection = new \ReflectionClass(TenantCertificateController::class);

        static::assertSame([], $reflection->getAttributes(IsGranted::class));
        static::assertSame(
            ["perm:settings.edit"],
            $this->grantsFor($reflection->getMethod('__invoke')),
        );
        static::assertSame(
            ["perm:settings.view"],
            $this->grantsFor($reflection->getMethod('get')),
        );
    }

    public function testCertificateMetadataDoesNotExposeCredentialMaterial(): void
    {
        $controller = (new \ReflectionClass(TenantCertificateController::class))->newInstanceWithoutConstructor();
        $certificate = new TenantCertificate(
            id: 7,
            tenantId: 11,
            tenantName: 'tenant-a',
            ruc: '20123456789',
            status: CertificateStatus::Active,
            subjectCn: 'tenant-a',
            issuerCn: 'SUNAT',
            serialNumber: 'serial-7',
            fingerprintSha256: 'fingerprint-7',
            validFrom: new \DateTimeImmutable('2026-01-01'),
            expiresAt: new \DateTimeImmutable('2027-01-01'),
            uploadedAt: new \DateTimeImmutable('2026-01-01'),
            uploadedBy: 'admin',
            revokedAt: null,
            revokedBy: null,
            revocationReason: null,
            supersededById: null,
            lastAlertedAt: null,
        );

        $method = new \ReflectionMethod(TenantCertificateController::class, 'serializeCertificate');
        /** @var array<string, mixed> $payload */
        $payload = $method->invoke($controller, $certificate);

        static::assertIsArray($payload);
        static::assertArrayNotHasKey('certificatePem', $payload);
        static::assertArrayNotHasKey('privateKey', $payload);
        static::assertArrayNotHasKey('solPass', $payload);
        static::assertArrayNotHasKey('certificatePassword', $payload);
        static::assertSame('fingerprint-7', $payload['fingerprintSha256']);
    }

    /**
     * @return list<string>
     */
    private function grantsFor(\ReflectionMethod $method): array
    {
        return array_map(
            static function (\ReflectionAttribute $attribute): string {
                $instance = $attribute->newInstance();
                if (!is_string($instance->attribute)) {
                    throw new \UnexpectedValueException('Expected a string authorization attribute.');
                }

                return $instance->attribute;
            },
            $method->getAttributes(IsGranted::class, \ReflectionAttribute::IS_INSTANCEOF),
        );
    }
}
