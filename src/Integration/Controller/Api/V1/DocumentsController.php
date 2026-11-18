<?php

declare(strict_types=1);

namespace App\Integration\Controller\Api\V1;

use App\Billing\Sunat\Domain\BillPdfUseCase;
use App\Integration\Entity\IntegrationDocument;
use App\Integration\Fiscal\DocumentInput;
use App\Integration\Fiscal\DocumentIntake;
use App\Integration\Fiscal\IdempotencyConflict;
use App\Integration\Fiscal\InvalidDocumentInput;
use App\Integration\Repository\IntegrationDocumentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * `POST /api/v1/documents` and `GET /api/v1/documents/{reference}` — the fiscal
 * emission contract consumed by nubit-control (see docs/efact-integration.md).
 */
final class DocumentsController extends AbstractController
{
    #[Route('/api/v1/documents', name: 'integration_v1_documents_create', methods: ['POST'])]
    #[IsGranted('ROLE_SCOPE_DOCUMENTS_WRITE')]
    public function create(Request $request, DocumentIntake $intake): JsonResponse
    {
        $body = json_decode($request->getContent(), associative: true);
        if (!is_array($body)) {
            return $this->error(
                'validation_failed',
                'The request body must be a JSON object.',
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            /** @var array<string, mixed> $body */
            $input = DocumentInput::fromArray($body);
            $document = $intake->submit($input, $body);
        } catch (InvalidDocumentInput $e) {
            return $this->error('validation_failed', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, $e->errors);
        } catch (IdempotencyConflict $e) {
            return $this->error('idempotency_conflict', $e->getMessage(), Response::HTTP_CONFLICT);
        }

        return new JsonResponse(
            [
                'reference' => $document->getReference(),
                'status' => $document->getStatus(),
                'external_id' => $document->getExternalId(),
            ],
            $document->getStatus() === IntegrationDocument::STATUS_QUEUED ? Response::HTTP_ACCEPTED : Response::HTTP_OK,
        );
    }

    #[Route('/api/v1/documents/{reference}', name: 'integration_v1_documents_show', methods: ['GET'])]
    #[IsGranted('ROLE_SCOPE_DOCUMENTS_READ')]
    public function show(string $reference, IntegrationDocumentRepository $documents): JsonResponse
    {
        $document = $documents->findOneByReference($reference);
        if (null === $document) {
            return $this->error('not_found', 'No document with that reference.', Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->represent($document));
    }

    /** The printable A4 PDF of an accepted document, for the caller to deliver to its customer. */
    #[Route('/api/v1/documents/{reference}/pdf', name: 'integration_v1_documents_pdf', methods: ['GET'])]
    #[IsGranted('ROLE_SCOPE_DOCUMENTS_READ')]
    public function pdf(string $reference, IntegrationDocumentRepository $documents, BillPdfUseCase $billPdf): Response
    {
        $document = $documents->findOneByReference($reference);
        if (null === $document) {
            return $this->error('not_found', 'No document with that reference.', Response::HTTP_NOT_FOUND);
        }
        $taxDocumentId = $document->getTaxDocumentId();
        if (IntegrationDocument::STATUS_ACCEPTED !== $document->getStatus() || null === $taxDocumentId) {
            return $this->error(
                'document_not_accepted',
                'The PDF is available once SUNAT has accepted the document.',
                Response::HTTP_CONFLICT,
            );
        }

        $file = $billPdf($taxDocumentId);

        return new Response($file->file, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $file->name . '"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function represent(IntegrationDocument $document): array
    {
        return [
            'reference' => $document->getReference(),
            'external_id' => $document->getExternalId(),
            'status' => $document->getStatus(),
            'sunat_status' => $document->getSunatStatus(),
            'document_type' => $document->getDocumentType(),
            'series' => $document->getSeries(),
            'number' => $document->getNumber(),
            'cdr_reference' => $document->getCdrReference(),
            'error' => $document->getError(),
            'created_at' => $document->getCreatedAt()->format(\DATE_ATOM),
            'updated_at' => $document->getUpdatedAt()->format(\DATE_ATOM),
        ];
    }

    /**
     * @param list<array{field: string, issue: string}> $details
     */
    private function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ([] !== $details) {
            $error['details'] = $details;
        }

        return new JsonResponse(['error' => $error], $status);
    }
}
