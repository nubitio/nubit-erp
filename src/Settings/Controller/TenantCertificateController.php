<?php

declare(strict_types=1);

namespace App\Settings\Controller;

use App\ControlPlane\Certificate\CertificateLifecycleServiceInterface;
use App\ControlPlane\Certificate\TenantCertificate;
use App\Organization\Repository\CompanyRepository;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tenant-facing SUNAT certificate setup endpoint.
 *
 * The lifecycle and Lycet sync remain in the control plane; this controller is
 * the tenant-scoped adapter used by onboarding/settings.
 */
#[Route('/api/tenant')]
#[IsGranted('ROLE_USER')]
final class TenantCertificateController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly CompanyRepository $companyRepository,
        private readonly CertificateLifecycleServiceInterface $certificates,
    ) {}

    #[Route('/certificate', name: 'api_tenant_certificate_upsert', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $tenantName = $this->tenantContext->getTenantName();
        if ($tenantName === null) {
            return $this->json(['error' => 'No tenant context'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = $this->readPayload($request);
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
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'No se pudo configurar el certificado SUNAT.'], Response::HTTP_BAD_GATEWAY);
        }

        return $this->json([
            'certificate' => $this->serializeCertificate($certificate),
        ]);
    }

    #[Route('/certificate', name: 'api_tenant_certificate_get', methods: ['GET'])]
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
        if (is_string($requestedRuc) && preg_match('/^\d{11}$/', $requestedRuc) === 1) {
            return $requestedRuc;
        }

        $company = $this->companyRepository->findOneBy([]);
        if ($company !== null) {
            return $company->getRuc();
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
    private function serializeCertificate(TenantCertificate $certificate): array
    {
        return [
            'id' => $certificate->id,
            'ruc' => $certificate->ruc,
            'status' => $certificate->status->value,
            'subjectCn' => $certificate->subjectCn,
            'issuerCn' => $certificate->issuerCn,
            'serialNumber' => $certificate->serialNumber,
            'fingerprintSha256' => $certificate->fingerprintSha256,
            'validFrom' => $certificate->validFrom->format(DATE_ATOM),
            'expiresAt' => $certificate->expiresAt->format(DATE_ATOM),
            'uploadedAt' => $certificate->uploadedAt->format(DATE_ATOM),
            'lycetSyncStatus' => $certificate->lycetSyncStatus,
        ];
    }
}
