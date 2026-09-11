<?php

declare(strict_types=1);

namespace App\Settings\Controller;

use App\ControlPlane\Certificate\CertificateLifecycleServiceInterface;
use App\ControlPlane\Certificate\CertificateMetadata;
use App\Efact\Audit\AuditLogger;
use App\Identity\Entity\User;
use App\Organization\Repository\CompanyRepository;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tenant-facing SUNAT certificate setup endpoint.
 *
 * The lifecycle and Lycet sync remain in the control plane; this controller is
 * the tenant-scoped adapter used by onboarding/settings.
 */
#[Route('/api/tenant')]
final class TenantCertificateController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly CompanyRepository $companyRepository,
        private readonly CertificateLifecycleServiceInterface $certificates,
        private readonly AuditLogger $auditLogger,
        private readonly Security $security,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    #[Route('/certificate', name: 'api_tenant_certificate_upsert', methods: ['POST'])]
    #[IsGranted('perm:settings.edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(Request $request): JsonResponse
    {
        $tenantName = $this->tenantContext->getTenantName();
        if ($tenantName === null) {
            return $this->json(['error' => 'No tenant context'], Response::HTTP_UNAUTHORIZED);
        }

        // A permission/role in a long-lived JWT is not step-up authentication.
        // Require the current user's password for this credential-bearing write.
        $user = $this->security->getUser();
        $payload = $this->readPayload($request);
        $reauthPassword = $payload['reauthPassword'] ?? $payload['currentPassword'] ?? null;
        if (!$user instanceof User || !is_string($reauthPassword) || $reauthPassword === ''
            || !$this->passwordHasher->isPasswordValid($user, $reauthPassword)) {
            return $this->json(['error' => 'Reautenticacion requerida.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $ruc = $this->resolveRuc($payload['ruc'] ?? null);
            $solUser = $this->solUserWithRuc($this->requiredString($payload, 'solUser'), $ruc);
            $solPass = $this->requiredString($payload, 'solPass');
            $certificatePem = $this->extractCertificatePem($request, $payload);

            $certificate = $this->certificates->upsert(
                $tenantName,
                $ruc,
                $solUser,
                $solPass,
                $certificatePem,
                $this->getUser()?->getUserIdentifier() ?? 'tenant-api',
                function (int $_id, CertificateMetadata $metadata, array $_tenant) use ($ruc, $tenantName): void {
                    $this->auditLogger->logDurably('tenant.certificate.upsert', [
                        'outcome' => 'success',
                        'tenant_name' => $tenantName,
                        'ruc' => $ruc,
                        'fingerprint_sha256' => $metadata->fingerprintSha256,
                    ]);
                },
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (AccessDeniedHttpException) {
            return $this->json(['error' => 'El RUC no pertenece al tenant actual.'], Response::HTTP_FORBIDDEN);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'No se pudo configurar el certificado SUNAT.'], Response::HTTP_BAD_GATEWAY);
        }

        return $this->json([
            'certificate' => $this->serializeCertificate($certificate),
        ]);
    }

    #[Route('/certificate', name: 'api_tenant_certificate_get', methods: ['GET'])]
    #[IsGranted('perm:settings.view')]
    #[IsGranted('ROLE_ADMIN')]
    public function get(): JsonResponse
    {
        $tenantName = $this->tenantContext->getTenantName();
        if ($tenantName === null) {
            return $this->json(['error' => 'No tenant context'], Response::HTTP_UNAUTHORIZED);
        }

        $ruc = $this->resolveRuc(null);
        foreach ($this->certificates->list($tenantName) as $certificate) {
            if ($certificate->ruc === $ruc && $certificate->revokedAt === null) {
                return $this->json(['certificate' => $this->serializeCertificate($certificate)]);
            }
        }

        return $this->json(['certificate' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function readPayload(Request $request): array
    {
        $payload = $request->request->all();
        if ($payload !== []) {
            return $payload;
        }

        try {
            return $request->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    private function resolveRuc(mixed $requestedRuc): string
    {
        if ($requestedRuc !== null && (!is_string($requestedRuc) || preg_match('/^\d{11}$/', $requestedRuc) !== 1)) {
            throw new \InvalidArgumentException('El RUC debe tener 11 digitos.');
        }

        $company = is_string($requestedRuc)
            ? $this->companyRepository->findOneBy(['ruc' => $requestedRuc])
            : $this->companyRepository->findOneBy([]);
        if ($company !== null) {
            return $company->getRuc();
        }

        if (is_string($requestedRuc)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('El RUC no pertenece al tenant actual.');
        }

        throw new \InvalidArgumentException('Debe indicar el RUC de la empresa.');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException(sprintf('Debe indicar "%s".', $key));
        }

        return trim($value);
    }

    private function solUserWithRuc(string $solUser, string $ruc): string
    {
        return $ruc . (preg_replace('/^' . preg_quote($ruc, '/') . '/', '', $solUser) ?? $solUser);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractCertificatePem(Request $request, array $payload): string
    {
        $uploaded = $request->files->get('certificate');
        if ($uploaded instanceof UploadedFile) {
            $contents = file_get_contents($uploaded->getPathname());
            if (!is_string($contents) || $contents === '') {
                throw new \InvalidArgumentException('El archivo de certificado esta vacio.');
            }

            return $this->certificateContentsToPem($contents, $this->optionalCertificatePassword($payload));
        }

        $certificatePem = $payload['certificatePem'] ?? null;
        if (is_string($certificatePem) && trim($certificatePem) !== '') {
            return $this->certificateContentsToPem(trim($certificatePem), $this->optionalCertificatePassword($payload));
        }

        $certificateBase64 = $payload['certificateBase64'] ?? null;
        if (is_string($certificateBase64) && trim($certificateBase64) !== '') {
            $decoded = base64_decode($certificateBase64, true);
            if (!is_string($decoded) || $decoded === '') {
                throw new \InvalidArgumentException('El certificado base64 no es valido.');
            }

            return $this->certificateContentsToPem($decoded, $this->optionalCertificatePassword($payload));
        }

        throw new \InvalidArgumentException('Debe adjuntar el certificado SUNAT.');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function optionalCertificatePassword(array $payload): string
    {
        $password = $payload['certificatePassword'] ?? $payload['pfxPassword'] ?? '';

        return is_string($password) ? $password : '';
    }

    private function certificateContentsToPem(string $contents, string $password): string
    {
        if (str_contains($contents, '-----BEGIN CERTIFICATE-----')) {
            if (!str_contains($contents, 'PRIVATE KEY-----')) {
                throw new \InvalidArgumentException('El PEM debe incluir el certificado y su clave privada.');
            }

            return $contents;
        }

        $certs = [];
        if (!openssl_pkcs12_read($contents, $certs, $password)) {
            throw new \InvalidArgumentException('No se pudo leer el certificado PFX. Verifica la contraseña.');
        }

        $cert = isset($certs['cert']) && is_string($certs['cert']) ? $certs['cert'] : '';
        if ($cert === '') {
            throw new \InvalidArgumentException('El PFX no contiene un certificado valido.');
        }

        $privateKey = isset($certs['pkey']) && is_string($certs['pkey']) ? $certs['pkey'] : '';

        return $cert . ($privateKey === '' ? '' : "\n" . $privateKey);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCertificate(object $certificate): array
    {
        $metadata = get_object_vars($certificate);
        /** @var array{
         *     id: int,
         *     ruc: string,
         *     status: \BackedEnum,
         *     subjectCn: ?string,
         *     issuerCn: ?string,
         *     serialNumber: ?string,
         *     fingerprintSha256: string,
         *     validFrom: \DateTimeImmutable,
         *     expiresAt: \DateTimeImmutable,
         *     uploadedAt: \DateTimeImmutable,
         *     lycetSyncStatus: string,
         * } $metadata */

        return [
            'id' => $metadata['id'],
            'ruc' => $metadata['ruc'],
            'status' => $metadata['status']->value,
            'subjectCn' => $metadata['subjectCn'],
            'issuerCn' => $metadata['issuerCn'],
            'serialNumber' => $metadata['serialNumber'],
            'fingerprintSha256' => $metadata['fingerprintSha256'],
            'validFrom' => $metadata['validFrom']->format(DATE_ATOM),
            'expiresAt' => $metadata['expiresAt']->format(DATE_ATOM),
            'uploadedAt' => $metadata['uploadedAt']->format(DATE_ATOM),
            'lycetSyncStatus' => $metadata['lycetSyncStatus'],
        ];
    }

}
