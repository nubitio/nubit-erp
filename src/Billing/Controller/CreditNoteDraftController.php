<?php

declare(strict_types=1);

namespace App\Billing\Controller;

use App\Billing\Entity\CreditNoteDraft;
use App\Billing\Entity\TaxDocument;
use App\Billing\Repository\CreditNoteDraftRepository;
use App\Billing\Repository\TaxDocumentRepository;
use App\Billing\Service\CreditNoteLineChoices;
use App\Billing\Shared\Enum\CreditNoteReason;
use App\Identity\Auth\BranchContextService;
use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Exception\ServiceException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Personal drafts of credit notes. A draft stores only what the user chose;
 * every amount is recomputed from the affected document when it is issued.
 */
#[Route('/api/credit-notes/drafts')]
final class CreditNoteDraftController extends AbstractController
{
    public function __construct(
        private readonly CreditNoteDraftRepository $drafts,
        private readonly TaxDocumentRepository $taxDocuments,
        private readonly BranchContextService $branchContext,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('', name: 'credit_note_drafts_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        [$branchId, $userId] = $this->owner();

        return $this->json(array_map($this->present(...), $this->drafts->listOwned($branchId, $userId)));
    }

    #[Route('/{id}', name: 'credit_note_drafts_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        [$branchId, $userId] = $this->owner();

        return $this->json($this->present($this->owned($id, $branchId, $userId)));
    }

    /** Creates a draft, or replaces the one named by `id`. */
    #[Route('', name: 'credit_note_drafts_save', methods: ['PUT'])]
    public function save(Request $request): JsonResponse
    {
        [$branchId, $userId] = $this->owner();
        $payload = $request->toArray();

        $relatedId = (string) ($payload['relatedTaxDocument'] ?? '');
        $related = $relatedId !== '' ? $this->taxDocuments->find($relatedId) : null;
        if (!$related instanceof TaxDocument || $related->getBranch()?->getId() !== $branchId) {
            throw new ServiceException('El comprobante afectado no existe en la sucursal actual.');
        }

        $reasonCode = ($payload['reasonCode'] ?? '') !== '' ? (string) $payload['reasonCode'] : null;
        if ($reasonCode !== null && CreditNoteReason::tryFrom($reasonCode) === null) {
            throw new ServiceException('El motivo de la nota de crédito no es válido (catálogo SUNAT 09).');
        }
        $description = trim((string) ($payload['reasonDescription'] ?? ''));
        $description = $description !== '' ? mb_substr($description, start: 0, length: 250) : null;
        $lines = CreditNoteLineChoices::normalize($payload['lines'] ?? []);

        $id = (string) ($payload['id'] ?? '');
        $draft = ($id !== '' ? $this->owned($id, $branchId, $userId) : null) ?? new CreditNoteDraft(
            $branchId,
            $userId,
            $relatedId,
            $reasonCode,
            $description,
            $lines,
        );
        $draft->replaceContent($relatedId, $reasonCode, $description, $lines);
        $this->entityManager->persist($draft); // a no-op when the draft is already managed
        $this->entityManager->flush();

        return $this->json($this->present($draft));
    }

    #[Route('/{id}', name: 'credit_note_drafts_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        [$branchId, $userId] = $this->owner();
        $this->entityManager->remove($this->owned($id, $branchId, $userId));
        $this->entityManager->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function owner(): array
    {
        $this->denyAccessUnlessGranted('perm:sales.void');
        $user = $this->getUser();
        $branchId = $this->branchContext->getCurrentBranchId();
        if (!$user instanceof User || $branchId === null) {
            throw new ServiceException('Selecciona una sucursal para guardar borradores.');
        }

        return [$branchId, (int) $user->getId()];
    }

    private function owned(string $id, string $branchId, int $userId): CreditNoteDraft
    {
        return (
            $this->drafts->findOwned($id, $branchId, $userId) ?? throw $this->createNotFoundException(
                'El borrador no existe.',
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CreditNoteDraft $draft): array
    {
        $related = $this->taxDocuments->find($draft->getRelatedTaxDocumentId());

        return [
            'id' => $draft->getId(),
            'relatedTaxDocument' => $draft->getRelatedTaxDocumentId(),
            'relatedFullNumber' => $related?->getFullNumber(),
            'reasonCode' => $draft->getReasonCode(),
            'reasonDescription' => $draft->getReasonDescription(),
            'lines' => $draft->getLines(),
            'updatedAt' => $draft->getUpdatedAt()->format(DATE_ATOM),
        ];
    }
}
