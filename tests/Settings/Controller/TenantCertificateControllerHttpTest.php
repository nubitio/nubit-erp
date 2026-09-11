<?php

declare(strict_types=1);

namespace App\Tests\Settings\Controller;

use App\ControlPlane\Certificate\CertificateLifecycleServiceInterface;
use App\ControlPlane\Certificate\CertificateStatus;
use App\ControlPlane\Certificate\TenantCertificate;
use App\Efact\Audit\AuditLogger;
use App\Identity\Security\ReauthenticationService;
use App\Organization\Entity\Company;
use App\Organization\Repository\CompanyRepository;
use App\Settings\Controller\TenantCertificateController;
use App\Settings\Service\TenantCertificateIdentity;
use App\Settings\Service\TenantCertificatePemDecoder;
use App\Settings\Service\TenantCertificateRequest;
use Nubit\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** Request-level coverage for the tenant certificate mutation boundary. */
final class TenantCertificateControllerHttpTest extends TestCase
{
    private TenantContext&MockObject $tenantContext;
    private CompanyRepository&MockObject $companies;
    private CertificateLifecycleServiceInterface&MockObject $certificates;
    private AuditLogger&MockObject $audit;
    private LoggerInterface&MockObject $logger;
    private UserPasswordHasherInterface&MockObject $hasher;

    protected function setUp(): void
    {
        $this->tenantContext = $this->createMock(TenantContext::class);
        $this->companies = $this->createMock(CompanyRepository::class);
        $this->certificates = $this->createMock(CertificateLifecycleServiceInterface::class);
        $this->audit = $this->createMock(AuditLogger::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->hasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->tenantContext->method('getTenantName')->willReturn('tenant-a');
        $this->companies->method('findOneBy')->willReturn(new Company()->setRuc('20123456789'));
    }

    public function testCrossTenantRucIsRejectedBeforeAnyCertificateMutation(): void
    {
        $currentPassword = bin2hex(random_bytes(16));
        $this->hasher->method('isPasswordValid')->willReturn(true);
        $this->certificates->expects(self::never())->method('upsert');

        $response = $this->controller()->__invoke($this->request([
            'ruc' => '20987654321',
            'currentPassword' => $currentPassword,
        ]));

        self::assertSame(400, $response->getStatusCode());
        self::assertStringNotContainsString($currentPassword, (string) $response->getContent());
    }

    public function testMissingOrInvalidStepUpDoesNotMutate(): void
    {
        $this->hasher->expects(self::never())->method('isPasswordValid');
        $this->certificates->expects(self::never())->method('upsert');

        $response = $this->controller()->__invoke($this->request([
            'ruc' => '20123456789',
        ]));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testMutationResponseContainsMetadataOnly(): void
    {
        $solPassword = bin2hex(random_bytes(16));
        $certificatePassword = bin2hex(random_bytes(16));
        $currentPassword = bin2hex(random_bytes(16));
        $certificatePem = "-----BEGIN CERTIFICATE-----\nprivate\n-----PRIVATE KEY-----";
        $this->hasher->method('isPasswordValid')->willReturn(true);
        $this->certificates
            ->expects(self::once())
            ->method('upsert')
            ->with('tenant-a', '20123456789', '20123456789user', $solPassword, $certificatePem, 'admin')
            ->willReturn(new TenantCertificate(
                id: 1,
                tenantId: 1,
                tenantName: 'tenant-a',
                ruc: '20123456789',
                status: CertificateStatus::Active,
                subjectCn: 'tenant-a',
                issuerCn: 'SUNAT',
                serialNumber: 'serial',
                fingerprintSha256: 'fingerprint',
                validFrom: new \DateTimeImmutable(),
                expiresAt: new \DateTimeImmutable('+1 year'),
                uploadedAt: new \DateTimeImmutable(),
                uploadedBy: 'admin',
                revokedAt: null,
                revokedBy: null,
                revocationReason: null,
                supersededById: null,
                lastAlertedAt: null,
            ));

        $response = $this->controller()->__invoke($this->request([
            'ruc' => '20123456789',
            'solUser' => 'user',
            'solPass' => $solPassword,
            'certificatePem' => $certificatePem,
            'currentPassword' => $currentPassword,
            'certificatePassword' => $certificatePassword,
        ]));

        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getContent();
        self::assertStringNotContainsString($solPassword, $body);
        self::assertStringNotContainsString($certificatePassword, $body);
        self::assertStringNotContainsString('PRIVATE KEY', $body);
    }

    /** @param array<string, string> $payload */
    private function request(array $payload): Request
    {
        return Request::create(
            '/api/tenant/certificate',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    private function controller(): TenantCertificateController
    {
        $user = new class implements UserInterface, PasswordAuthenticatedUserInterface {
            public function getRoles(): array
            {
                return ['ROLE_ADMIN'];
            }

            public function eraseCredentials(): void {}

            public function getUserIdentifier(): string
            {
                return 'admin';
            }

            public function getPassword(): string
            {
                return 'hash';
            }
        };
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(new UsernamePasswordToken($user, 'main', ['ROLE_ADMIN']));

        $container = new \Symfony\Component\DependencyInjection\Container();
        $container->set('security.token_storage', $tokenStorage);

        $controller = new TenantCertificateController(
            $this->tenantContext,
            new TenantCertificateRequest(new TenantCertificatePemDecoder()),
            new TenantCertificateIdentity($this->companies),
            $this->certificates,
            $this->audit,
            $this->logger,
            new ReauthenticationService($this->hasher),
        );
        $controller->setContainer($container);

        return $controller;
    }
}
