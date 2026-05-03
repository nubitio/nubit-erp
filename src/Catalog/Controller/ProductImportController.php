<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Message\ProductImportMessage;
use App\Catalog\Service\ProductImportTemplateService;
use Nubit\Platform\Tenant\Context\TenantContext;
use Nubit\Platform\Tenant\Contract\TenantRegistryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/products/import')]
final class ProductImportController extends AbstractController
{
    public function __construct(
        private readonly ProductImportTemplateService $templateService,
        private readonly MessageBusInterface $bus,
        private readonly TenantContext $tenantContext,
        private readonly TenantRegistryInterface $tenantRegistry,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * GET /api/products/import/template
     * Returns a downloadable .xlsx import template pre-populated with category
     * and unit-of-measure dropdowns from the current tenant's DB.
     */
    #[Route('/template', name: 'product_import_template', methods: ['GET'])]
    public function template(): StreamedResponse
    {
        $spreadsheet = $this->templateService->build();

        $response = new StreamedResponse(function () use ($spreadsheet): void {
            $this->templateService->stream($spreadsheet);
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="plantilla-productos.xlsx"');
        $response->headers->set('Cache-Control', 'no-cache, no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    /**
     * POST /api/products/import
     * Accepts a multipart upload of an .xlsx file, stores it in a temp
     * directory and dispatches an async ProductImportMessage.
     *
     * Returns 202 Accepted with the jobId the client should subscribe to
     * for real-time progress via Mercure SSE.
     */
    #[Route('', name: 'product_import_upload', methods: ['POST'])]
    public function upload(Request $request): Response
    {
        /** @var ?\Symfony\Component\HttpFoundation\File\UploadedFile $file */
        $file = $request->files->get('file');

        if ($file === null) {
            throw new BadRequestHttpException('El parámetro "file" es obligatorio.');
        }

        $allowedMimes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/octet-stream',
        ];
        $mime = $file->getMimeType() ?? '';
        $ext  = strtolower($file->getClientOriginalExtension());

        if ($ext !== 'xlsx' && !in_array($mime, $allowedMimes, true)) {
            throw new BadRequestHttpException('Solo se aceptan archivos .xlsx.');
        }

        // Store the file in var/import-tmp/ — the handler will delete it after processing.
        $uploadDir = $this->projectDir . '/var/import-tmp';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0o755, true);
        }

        $jobId    = Uuid::v4()->toRfc4122();
        $filename = $jobId . '.xlsx';
        $file->move($uploadDir, $filename);
        $filePath = $uploadDir . '/' . $filename;

        // Build public origin: honour X-Forwarded-Host (set by reverse proxy) so the
        // topic matches what the browser builds from window.location.origin.
        $forwardedHost = $request->headers->get('X-Forwarded-Host');
        $forwardedProto = $request->headers->get('X-Forwarded-Proto', $request->getScheme());
        if ($forwardedHost) {
            $tenantOrigin = $forwardedProto . '://' . $forwardedHost;
        } else {
            $tenantDomain = $this->tenantContext->getTenantDomain();
            $tenantOrigin = $tenantDomain
                ? $request->getScheme() . '://' . $tenantDomain
                : $request->getSchemeAndHttpHost();
        }

        $tenantHost = $forwardedHost ?: ($this->tenantContext->getTenantDomain() ?: $request->getHost());
        $tenant = $this->resolveTenantFromContextOrHost($tenantHost);

        $topic = $tenantOrigin . '/api/product-imports/' . $jobId;

        $this->bus->dispatch(new ProductImportMessage(
            jobId:        $jobId,
            filePath:     $filePath,
            tenantOrigin: $tenantOrigin,
            tenantName:   $tenant['name'] ?? null,
            tenantId:     isset($tenant['id']) ? (int) $tenant['id'] : null,
            tenantDomain: $tenant['domain'] ?? $tenantHost,
            requestId:    (string) ($request->attributes->get('request_id') ?? ''),
        ));

        return $this->json([
            'jobId'  => $jobId,
            'status' => 'queued',
            'topic'  => $topic,
        ], Response::HTTP_ACCEPTED);
    }

    /** @return array<string, mixed>|null */
    private function resolveTenantFromContextOrHost(string $host): ?array
    {
        $tenantName = $this->tenantContext->getTenantName();
        if ($tenantName !== null && $tenantName !== '') {
            return [
                'id'     => $this->tenantContext->getTenantId(),
                'name'   => $tenantName,
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
