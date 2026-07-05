<?php

declare(strict_types=1);

namespace App\ControlPlane\Operator\Api;

use App\ControlPlane\Certificate\CertificateLifecycleServiceInterface;
use App\ControlPlane\Certificate\CertificatePresenter;
use App\ControlPlane\Operator\Application\TenantDetailQueryService;
use App\ControlPlane\Operator\Application\TenantOperatorCommandService;
use App\ControlPlane\Tenant\Application\TenantService;
use App\ControlPlane\Tenant\Service\TenantBackupService;
use App\Efact\Audit\AuditLogger;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CP_VIEWER')]
#[Route('/api/control-plane/tenants')]
final class TenantApiController extends AbstractController
{
    public function __construct(
        private readonly TenantService $tenantService,
        private readonly TenantDetailQueryService $detailQuery,
        private readonly TenantOperatorCommandService $commands,
        private readonly TenantBackupService $backupService,
        private readonly CertificateLifecycleServiceInterface $certificateLifecycleService,
        private readonly AuditLogger $auditLogger,
    ) {}

    #[Route('', name: 'api_control_plane_tenants_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'tenants' => $this->tenantService->getTenants(),
            'deletedTenants' => $this->tenantService->getDeletedTenants(),
        ]);
    }

    #[Route('/{id}', name: 'api_control_plane_tenants_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $detail = $this->detailQuery->getDetail($id);
        if ($detail === null) {
            return $this->json(['error' => 'Tenant not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($detail);
    }

    #[Route('/{id}/usage', name: 'api_control_plane_tenants_usage', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function usage(int $id): JsonResponse
    {
        try {
            return $this->json($this->detailQuery->getUsage($id));
        } catch (InvalidArgumentException) {
            return $this->json(['error' => 'Tenant not found'], Response::HTTP_NOT_FOUND);
        }
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('', name: 'api_control_plane_tenants_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $this->tenantService->addTenant($payload);
            $this->auditLogger->log('tenant.create', ['tenant' => $payload]);
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true], Response::HTTP_CREATED);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', name: 'api_control_plane_tenants_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $this->tenantService->updateTenant($id, $payload);
            $this->auditLogger->log('tenant.update', ['tenant_id' => $id, 'tenant' => $payload]);
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}/plan', name: 'api_control_plane_tenants_plan', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function changePlan(int $id, Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $plan = trim((string) ($payload['plan'] ?? ''));

        if ($plan === '') {
            return $this->json(['error' => 'Plan is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->commands->changePlan($id, $plan);
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true, 'plan' => $plan]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(
        '/{id}/suspend',
        name: 'api_control_plane_tenants_suspend',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function suspend(int $id): JsonResponse
    {
        try {
            $this->tenantService->suspendTenant($id);
            $this->auditLogger->log('tenant.suspend', ['tenant_id' => $id]);
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(
        '/{id}/unsuspend',
        name: 'api_control_plane_tenants_unsuspend',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function unsuspend(int $id): JsonResponse
    {
        try {
            $this->tenantService->unsuspendTenant($id);
            $this->auditLogger->log('tenant.unsuspend', ['tenant_id' => $id]);
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true]);
    }

    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[Route('/{id}', name: 'api_control_plane_tenants_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->commands->softDeleteTenant($id);

        return $this->json(['ok' => true]);
    }

    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[Route('/{id}/purge', name: 'api_control_plane_tenants_purge', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function purge(int $id): JsonResponse
    {
        try {
            $this->commands->purgeTenant($id);
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(
        '/{id}/restore',
        name: 'api_control_plane_tenants_restore',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function restore(int $id): JsonResponse
    {
        try {
            $this->commands->restoreTenant($id);
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(
        '/{id}/domains',
        name: 'api_control_plane_tenants_domains_create',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function addDomain(int $id, Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $this->commands->addDomain(
                $id,
                trim((string) ($payload['domain'] ?? '')),
                (bool) ($payload['isPrimary'] ?? false),
            );
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(
        '/{tenantId}/domains/{domainId}/primary',
        name: 'api_control_plane_tenants_domains_primary',
        requirements: ['tenantId' => '\d+', 'domainId' => '\d+'],
        methods: ['POST'],
    )]
    public function setPrimaryDomain(int $tenantId, int $domainId): JsonResponse
    {
        try {
            $this->commands->setPrimaryDomain($tenantId, $domainId);
        } catch (\Throwable $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(
        '/{tenantId}/domains/{domainId}',
        name: 'api_control_plane_tenants_domains_delete',
        requirements: ['tenantId' => '\d+', 'domainId' => '\d+'],
        methods: ['DELETE'],
    )]
    public function deleteDomain(int $tenantId, int $domainId): JsonResponse
    {
        $this->commands->deleteDomain($tenantId, $domainId);

        return $this->json(['ok' => true]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(
        '/{id}/provision',
        name: 'api_control_plane_tenants_provision',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function provision(int $id, Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $result = $this->commands->provision(
                $id,
                trim((string) ($payload['adminUser'] ?? 'admin')),
                (string) ($payload['adminPassword'] ?? ''),
                trim((string) ($payload['domain'] ?? '')),
            );
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        if (!$result['ok']) {
            return $this->json([
                'error' => 'Provisioning failed',
                'provisionId' => $result['provisionId'],
                'output' => $result['output'] ?? null,
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true, 'provisionId' => $result['provisionId']]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(
        '/{id}/backup-schedule',
        name: 'api_control_plane_tenants_backup_schedule',
        requirements: ['id' => '\d+'],
        methods: ['PATCH'],
    )]
    public function updateBackupSchedule(int $id, Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $this->commands->updateBackupSchedule(
                $id,
                (int) ($payload['backupFrequency'] ?? 0),
                (int) ($payload['backupRetentionDays'] ?? 30),
            );
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true]);
    }

    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[Route(
        '/{id}/backups',
        name: 'api_control_plane_tenants_backups_create',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function createBackup(int $id): JsonResponse
    {
        try {
            $this->commands->queueBackup($id);
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['ok' => true], Response::HTTP_ACCEPTED);
    }

    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[Route(
        '/{id}/backups/{backupId}/restore',
        name: 'api_control_plane_tenants_backups_restore',
        requirements: ['id' => '\d+', 'backupId' => '\d+'],
        methods: ['POST'],
    )]
    public function restoreBackup(int $id, int $backupId): JsonResponse
    {
        try {
            $this->commands->restoreBackup($id, $backupId);
        } catch (\Throwable $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true]);
    }

    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[Route(
        '/{id}/backups/{backupId}',
        name: 'api_control_plane_tenants_backups_delete',
        requirements: ['id' => '\d+', 'backupId' => '\d+'],
        methods: ['DELETE'],
    )]
    public function deleteBackup(int $id, int $backupId): JsonResponse
    {
        try {
            $this->commands->deleteBackup($id, $backupId);
        } catch (\Throwable $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true]);
    }

    #[IsGranted('ROLE_CP_VIEWER')]
    #[Route(
        '/{id}/backups/{backupId}/download',
        name: 'api_control_plane_tenants_backups_download',
        requirements: ['id' => '\d+', 'backupId' => '\d+'],
        methods: ['GET'],
    )]
    public function downloadBackup(int $id, int $backupId): StreamedResponse
    {
        $tenant = $this->tenantService->getTenant($id);
        if ($tenant === null) {
            throw $this->createNotFoundException('Tenant not found');
        }

        $backup = $this->backupService->findBackupForTenant($backupId, $id);
        if ($backup === null) {
            throw $this->createNotFoundException('Backup not found');
        }

        $localPath = $this->backupService->downloadBackup($backupId);
        $filename = (string) $backup['filename'];
        $isTemp = str_starts_with($localPath, sys_get_temp_dir());

        $response = new StreamedResponse(function () use ($localPath, $isTemp): void {
            $stream = fopen($localPath, 'r');
            if ($stream !== false) {
                fpassthru($stream);
                fclose($stream);
            }
            if ($isTemp) {
                @unlink($localPath);
            }
        });

        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    #[Route(
        '/{id}/certificates/{certificateId}',
        name: 'api_control_plane_tenants_certificates_show',
        requirements: ['id' => '\d+', 'certificateId' => '\d+'],
        methods: ['GET'],
    )]
    public function showCertificate(int $id, int $certificateId): JsonResponse
    {
        $tenant = $this->tenantService->getTenant($id);
        if ($tenant === null) {
            return $this->json(['error' => 'Tenant not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $certificate = $this->certificateLifecycleService->detail((string) $tenant['name'], $certificateId);
        } catch (\Throwable $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['certificate' => CertificatePresenter::toArray($certificate)]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(
        '/{id}/certificates',
        name: 'api_control_plane_tenants_certificates_upload',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function uploadCertificate(int $id, Request $request): JsonResponse
    {
        $tenant = $this->tenantService->getTenant($id);
        if ($tenant === null) {
            return $this->json(['error' => 'Tenant not found'], Response::HTTP_NOT_FOUND);
        }

        $file = $request->files->get('certificate');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'Certificate PEM file is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->certificateLifecycleService->upsert(
                (string) $tenant['name'],
                trim((string) $request->request->get('ruc', '')),
                trim((string) $request->request->get('solUser', '')),
                (string) $request->request->get('solPass', ''),
                (string) file_get_contents($file->getPathname()),
                $this->getUser()?->getUserIdentifier() ?? 'control-plane-api',
            );
            $this->auditLogger->log('tenant.certificate.upsert', [
                'tenant_id' => $id,
                'tenant_name' => (string) $tenant['name'],
                'ruc' => trim((string) $request->request->get('ruc', '')),
            ]);
        } catch (\Throwable $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true], Response::HTTP_CREATED);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(
        '/{id}/certificates/{ruc}/revoke',
        name: 'api_control_plane_tenants_certificates_revoke',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function revokeCertificate(int $id, string $ruc, Request $request): JsonResponse
    {
        $tenant = $this->tenantService->getTenant($id);
        if ($tenant === null) {
            return $this->json(['error' => 'Tenant not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $reason = trim((string) ($payload['reason'] ?? ''));
        if ($reason === '') {
            return $this->json(['error' => 'Revocation reason is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->certificateLifecycleService->revoke(
                (string) $tenant['name'],
                $ruc,
                $this->getUser()?->getUserIdentifier() ?? 'control-plane-api',
                $reason,
            );
            $this->auditLogger->log('tenant.certificate.revoke', [
                'tenant_id' => $id,
                'tenant_name' => (string) $tenant['name'],
                'ruc' => $ruc,
            ]);
        } catch (\Throwable $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true]);
    }
}
