<?php

declare(strict_types=1);

namespace App\Settings\Controller;

use App\Efact\Audit\AuditLogger;
use App\Identity\Security\ReauthenticationService;
use App\Settings\Contract\TenantCertificateServiceInterface;
use App\Settings\Dto\TenantCertificateView;
use App\Settings\Service\TenantCertificateIdentity;
use App\Settings\Service\TenantCertificateRequest;
use Nubit\Platform\Tenant\Context\TenantContext;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
final class TenantCertificateController extends AbstractController
{
    /** Keep tenant-facing certificate events aligned with the control-plane audit action. */
    private const string CERTIFICATE_UPSERT_AUDIT_ACTION = 'tenant.certificate.upsert';

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantCertificateRequest $certificateRequest,
        private readonly TenantCertificateIdentity $certificateIdentity,
        private readonly TenantCertificateServiceInterface $certificates,
        private readonly AuditLogger $auditLogger,
        private readonly LoggerInterface $logger,
        private readonly ReauthenticationService $reauthentication,
    ) {}

    #[Route('/certificate', name: 'api_tenant_certificate_upsert', methods: ['POST'])]
    #[IsGranted('perm:settings.edit')]
    public function __invoke(Request $request): JsonResponse
    {
        $tenantName = $this->tenantContext->getTenantName();
        if ($tenantName === null) {
            return $this->json(['error' => 'No tenant context'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = $this->certificateRequest->payload($request);
            $password = $payload['currentPassword'] ?? $payload['reauthPassword'] ?? null;
            if (!is_string($password) || !$this->reauthentication->isValid($this->getUser(), $password)) {
                return $this->json([
                    'code' => 'certificate_reauthentication_required',
                    'error' => 'La contraseña actual del ERP es obligatoria y debe ser correcta.',
                ], Response::HTTP_BAD_REQUEST);
            }

            $ruc = $this->certificateIdentity->ruc($payload['ruc'] ?? null);
            $solUser = $this->certificateIdentity->solUserWithRuc(
                $this->certificateIdentity->requiredString($payload, 'solUser'),
                $ruc,
            );
            $solPass = $this->certificateIdentity->requiredString($payload, 'solPass');
            $certificatePem = $this->certificateRequest->certificatePem($request, $payload);

            $certificate = $this->certificates->upsert(
                $tenantName,
                $ruc,
                $solUser,
                $solPass,
                $certificatePem,
                $this->getUser()?->getUserIdentifier() ?? 'tenant-api',
                function (int $_id, object $metadata, array $_tenant) use ($ruc, $tenantName): void {
                    $certificateMetadata = get_object_vars($metadata);
                    $this->auditLogger->logDurably(self::CERTIFICATE_UPSERT_AUDIT_ACTION, [
                        'outcome' => 'success',
                        'tenant_name' => $tenantName,
                        'ruc' => $ruc,
                        'fingerprint_sha256' => $certificateMetadata['fingerprintSha256'],
                    ]);
                },
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            $this->logger->error('Tenant certificate mutation failed.', ['exception' => $e]);

            return $this->json(['error' => 'No se pudo configurar el certificado SUNAT.'], Response::HTTP_BAD_GATEWAY);
        }

        return $this->json([
            'certificate' => $this->serializeCertificate($certificate),
        ]);
    }

    #[Route('/certificate', name: 'api_tenant_certificate_get', methods: ['GET'])]
    #[IsGranted('perm:settings.view')]
    public function get(): JsonResponse
    {
        $tenantName = $this->tenantContext->getTenantName();
        if ($tenantName === null) {
            return $this->json(['error' => 'No tenant context'], Response::HTTP_UNAUTHORIZED);
        }

        $ruc = $this->certificateIdentity->ruc(null);
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
    private function serializeCertificate(TenantCertificateView $certificate): array
    {
        return [
            'id' => $certificate->id,
            'ruc' => $certificate->ruc,
            'status' => $certificate->status,
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
