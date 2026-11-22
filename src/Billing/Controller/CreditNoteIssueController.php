<?php

declare(strict_types=1);

namespace App\Billing\Controller;

use App\Billing\Entity\TaxDocument;
use App\Billing\Repository\TaxDocumentRepository;
use App\Billing\Service\CreditNoteLineChoices;
use App\Billing\Service\CreditNoteNumbering;
use App\Billing\Service\CreditNotePreviewRenderer;
use App\Billing\Shared\Enum\CreditNoteReason;
use App\Identity\Auth\BranchContextService;
use Nubit\Platform\Exception\ServiceException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Read-side helpers for the credit-note issue screen: find the document to
 * correct, show the number the note will get, and render an unsaved preview.
 * None of these write anything.
 */
#[Route('/api/credit-notes')]
final class CreditNoteIssueController extends AbstractController
{
    private const int SEARCH_LIMIT = 15;

    public function __construct(
        private readonly TaxDocumentRepository $taxDocuments,
        private readonly BranchContextService $branchContext,
        private readonly CreditNoteNumbering $numbering,
        private readonly CreditNotePreviewRenderer $preview,
    ) {}

    /** Accepted, non-annulled invoices and receipts of the current branch that match the typed text. */
    #[Route('/eligible-documents', name: 'credit_note_eligible_documents', methods: ['GET'])]
    public function eligible(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('perm:sales.void');
        $branchId = $this->currentBranch();

        $found = $this->taxDocuments->findAcceptedForCreditNote(
            $branchId,
            $request->query->getString('q'),
            self::SEARCH_LIMIT,
        );

        return $this->json(array_map($this->summary(...), $found));
    }

    /** The series and number the note would receive right now. Informative: the real one is assigned on issue. */
    #[Route('/next-number', name: 'credit_note_next_number', methods: ['GET'])]
    public function nextNumber(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('perm:sales.void');

        return $this->json($this->numbering->next($this->relatedFor($request->query->getString('documentId'))));
    }

    /** Renders the note the user is composing, as a PDF, without saving anything. */
    #[Route('/preview', name: 'credit_note_preview', methods: ['POST'])]
    public function preview(Request $request): Response
    {
        $this->denyAccessUnlessGranted('perm:sales.void');
        $payload = $request->toArray();

        $reason = CreditNoteReason::tryFrom((string) ($payload['reasonCode'] ?? '')) ?? throw new ServiceException(
            'Elige el tipo de nota de crédito para ver la vista previa.',
        );
        $pdf = $this->preview->render(
            $this->relatedFor((string) ($payload['relatedTaxDocument'] ?? '')),
            $reason,
            trim((string) ($payload['reasonDescription'] ?? '')),
            CreditNoteLineChoices::normalize($payload['lines'] ?? []),
        );

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="vista-previa-nota-credito.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function currentBranch(): string
    {
        return $this->branchContext->getCurrentBranchId() ?? throw new ServiceException('Selecciona una sucursal.');
    }

    private function relatedFor(string $documentId): TaxDocument
    {
        $related = $documentId !== '' ? $this->taxDocuments->find($documentId) : null;
        if (!$related instanceof TaxDocument || $related->getBranch()?->getId() !== $this->currentBranch()) {
            throw new ServiceException('El comprobante afectado no existe en la sucursal actual.');
        }
        if (!in_array($related->getDocumentType(), ['01', '03'], true)) {
            throw new ServiceException('Solo se emiten notas de crédito sobre facturas y boletas.');
        }
        if ($related->isVoided() || $this->taxDocuments->isAnnulled($related)) {
            throw new ServiceException('Este comprobante ya fue anulado.');
        }

        return $related;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(TaxDocument $document): array
    {
        $customer = $document->getCustomer();

        return [
            'id' => $document->getId(),
            'fullNumber' => $document->getFullNumber(),
            'documentType' => $document->getDocumentType(),
            'issueDate' => $document->getIssueDate()->format('Y-m-d'),
            'customerName' => $customer?->getBusinessName(),
            'customerDocument' => $customer?->getRuc(),
            'currency' => $document->getCurrency() ?? 'PEN',
            'total' => $document->getTotal(),
        ];
    }
}
