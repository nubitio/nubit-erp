<?php

declare(strict_types=1);

namespace App\BankReconciliation\Controller;

use App\BankReconciliation\Entity\BankAccount;
use App\BankReconciliation\Enum\BankImportProfile;
use App\BankReconciliation\Message\BankStatementImportMessage;
use App\BankReconciliation\Service\BankStatementImportService;
use App\Identity\Entity\User;
use App\Identity\Security\Security;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use Nubit\Platform\Tenant\Contract\TenantRegistryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /api/v1/bank-statement-imports
 * Accepts a multipart upload of a bank statement export, stores it in a
 * temp directory, creates the BankStatementImport job row synchronously,
 * and dispatches an async BankStatementImportMessage. Mirrors
 * App\Catalog\Controller\ProductImportController — a plain controller
 * (not an ApiResource custom Post) because the DTO-based state processors
 * don't read multipart UploadedFile bodies.
 */
#[Route('/api/v1/bank-statement-imports')]
final class BankStatementImportController extends AbstractController
{
    public function __construct(
        private readonly BankStatementImportService $importService,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
        private readonly Security $security,
        private readonly TenantContext $tenantContext,
        private readonly TenantRegistryInterface $tenantRegistry,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    #[Route('', name: 'bank_statement_import_upload', methods: ['POST'])]
    public function upload(Request $request): Response
    {
        $user = $this->security->connectedUser();
        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }
        $userRef = $this->entityManager->getReference(User::class, $user->userId);
        if (!$userRef instanceof User) {
            throw new NotFoundHttpException('Usuario no encontrado.');
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new BadRequestHttpException('El parámetro "file" es obligatorio.');
        }

        $bankAccountId = (string) $request->request->get('bankAccountId', '');
        if ($bankAccountId === '') {
            throw new BadRequestHttpException('El parámetro "bankAccountId" es obligatorio.');
        }
        $bankAccount = $this->entityManager->find(BankAccount::class, $bankAccountId);
        if (!$bankAccount instanceof BankAccount) {
            throw new NotFoundHttpException('Cuenta bancaria no encontrada.');
        }

        $bankProfileRaw = (string) $request->request->get('bankProfile', '');
        $bankProfile = BankImportProfile::tryFrom($bankProfileRaw);
        if ($bankProfile === null) {
            throw new BadRequestHttpException(sprintf('El parámetro "bankProfile" debe ser uno de: %s.', implode(
                ', ',
                BankImportProfile::labels(),
            )));
        }

        $allowedMimes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'text/csv',
            'application/csv',
            'application/octet-stream',
        ];
        $mime = $file->getMimeType() ?? '';
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['xlsx', 'csv'], true) && !in_array($mime, $allowedMimes, true)) {
            throw new BadRequestHttpException('Solo se aceptan archivos .xlsx o .csv.');
        }

        $uploadDir = $this->projectDir . '/var/import-tmp';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0o755, true);
        }

        $originalName = $file->getClientOriginalName();
        $import = $this->importService->startImport($bankAccount, $bankProfile->value, $originalName, $userRef);

        $filename = $import->getId() . '.' . $ext;
        $file->move($uploadDir, $filename);
        $filePath = $uploadDir . '/' . $filename;

        $forwardedHost = $request->headers->get('X-Forwarded-Host');
        $forwardedProto = $request->headers->get('X-Forwarded-Proto') ?? $request->getScheme();
        if ($forwardedHost) {
            $tenantOrigin = $forwardedProto . '://' . $forwardedHost;
        } else {
            $tenantDomain = $this->tenantContext->getTenantDomain();
            $tenantOrigin =
                $tenantDomain !== null && $tenantDomain !== ''
                    ? $request->getScheme() . '://' . $tenantDomain
                    : $request->getSchemeAndHttpHost();
        }

        $tenantHost = $forwardedHost ?: ($this->tenantContext->getTenantDomain() ?: $request->getHost());
        $tenant = $this->resolveTenantFromContextOrHost($tenantHost);
        $tenantName = $tenant['name'] ?? null;
        $tenantDomain = $tenant['domain'] ?? null;
        $tenantId = $tenant['id'] ?? null;
        $requestId = $request->attributes->get('request_id');

        $topic = $tenantOrigin . '/api/bank-statement-imports/' . $import->getId();

        $this->bus->dispatch(new BankStatementImportMessage(
            importId: (string) $import->getId(),
            filePath: $filePath,
            bankProfile: $bankProfile->value,
            tenantOrigin: $tenantOrigin,
            tenantName: is_string($tenantName) ? $tenantName : null,
            tenantId: is_numeric($tenantId) ? (int) $tenantId : null,
            tenantDomain: is_string($tenantDomain) ? $tenantDomain : $tenantHost,
            requestId: is_string($requestId) ? $requestId : '',
        ));

        return $this->json([
            'importId' => $import->getId(),
            'status' => 'queued',
            'topic' => $topic,
        ], Response::HTTP_ACCEPTED);
    }

    /** @return array<string, mixed>|null */
    private function resolveTenantFromContextOrHost(string $host): ?array
    {
        $tenantName = $this->tenantContext->getTenantName();
        if ($tenantName !== null && $tenantName !== '') {
            return [
                'id' => $this->tenantContext->getTenantId(),
                'name' => $tenantName,
                'domain' => $this->tenantContext->getTenantDomain(),
            ];
        }

        $host = strtolower(trim(explode(':', $host)[0]));
        if ($host === '') {
            return null;
        }

        $tenant = $this->tenantRegistry->getTenantByDomain($host);
        if ($tenant !== null) {
            return $tenant;
        }

        $subdomain = explode('.', $host)[0];
        if ($subdomain === '' || in_array($subdomain, ['localhost', 'dev', 'stg', 'host'], true)) {
            return null;
        }

        return $this->tenantRegistry->getTenantByName($subdomain);
    }
}
