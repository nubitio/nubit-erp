<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\Entity\AssistantProposedAction;
use App\AI\Service\ProposedActionFactory;
use App\Billing\Entity\TaxDocument;
use App\Billing\Entity\TaxDocumentLine;
use App\Billing\Repository\TaxDocumentRepository;
use App\Billing\Shared\Enum\CreditNoteReason;
use Doctrine\Common\Collections\Collection;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'propose_credit_note',
    description: 'Propose a SUNAT credit note (type 07) against an existing boleta or factura. '
    . 'Does NOT save or send to SUNAT. Pass taxDocumentId or series+number, and reasonCode from '
    . 'catalog 09 (01 anulación, 02 error RUC, 06 devolución total, 07 devolución ítem, …). '
    . 'Optional linesJson [{"lineNumber":1,"qty":1}] — omit to credit all original lines. '
    . 'If the CPE was rejected by SUNAT, use propose_replace_rejected instead. Never bypass SUNAT.',
    method: 'proposeCreditNote',
)]
final readonly class ProposeCreditNoteTool
{
    public function __construct(
        private ProposedActionFactory $factory,
        private TaxDocumentRepository $taxDocumentRepository,
    ) {}

    /**
     * @return array{error: string}|array{id: string, kind: string, summary: string, payload: array<string, mixed>, expiresAt: string}
     */
    public function proposeCreditNote(
        string $reasonCode,
        ?string $taxDocumentId = null,
        ?string $series = null,
        ?string $number = null,
        ?string $linesJson = null,
    ): array {
        $document = $this->resolve($taxDocumentId, $series, $number);
        if (!$document instanceof TaxDocument) {
            return ['error' => 'No se encontró el comprobante.'];
        }

        $documentType = $document->getDocumentType();
        if ($documentType === '07') {
            return ['error' => 'No se emite una nota de crédito sobre otra nota de crédito.'];
        }
        if (!in_array($documentType, ['01', '03'], true)) {
            return ['error' => 'Solo se puede emitir nota de crédito sobre una boleta o factura.'];
        }

        if ($document->isVoided()) {
            return ['error' => sprintf(
                'El comprobante %s-%s ya está anulado.',
                $document->getSeries(),
                $document->getNumber(),
            )];
        }

        if ($this->isRejected($document)) {
            return ['error' => sprintf(
                'El comprobante %s-%s fue rechazado por SUNAT. Usa propose_replace_rejected, no una nota de crédito.',
                $document->getSeries(),
                $document->getNumber(),
            )];
        }

        $reason = CreditNoteReason::tryFrom(trim($reasonCode));
        if ($reason === null) {
            return ['error' => 'El motivo no es válido (catálogo SUNAT 09). Usa 01, 02, 06, 07, etc.'];
        }

        if ($reason->annulsDocument() && $this->taxDocumentRepository->hasAnnullingCreditNote($document)) {
            return ['error' => sprintf(
                'El comprobante %s-%s ya tiene una nota de crédito que lo anula.',
                $document->getSeries(),
                $document->getNumber(),
            )];
        }

        $lines = $this->resolveLines($document, $linesJson);
        if (isset($lines['error']) && is_string($lines['error'])) {
            return ['error' => $lines['error']];
        }

        $summary = sprintf(
            'Nota de crédito %s (%s) sobre %s-%s%s. No se envía a SUNAT hasta que la emitas en Notas de crédito.',
            $reason->value,
            $reason->description(),
            $document->getSeries(),
            $document->getNumber(),
            $reason->annulsDocument() ? '; la venta quedará anulada' : '',
        );

        $action = $this->factory->propose(AssistantProposedAction::KIND_CREATE_CREDIT_NOTE, $summary, [
            'taxDocumentId' => (string) $document->getId(),
            'reasonCode' => $reason->value,
            'reasonDescription' => $reason->description(),
            'annuls' => $reason->annulsDocument(),
            'series' => $document->getSeries(),
            'number' => $document->getNumber(),
            'lines' => $lines,
        ]);

        return $action->toCard();
    }

    /**
     * @return array{error: string}|list<array{lineNumber: int, qty: string}>
     */
    private function resolveLines(TaxDocument $document, ?string $linesJson): array
    {
        /** @var Collection<int, TaxDocumentLine> $originalLines */
        $originalLines = $document->getLines();
        if ($originalLines->isEmpty()) {
            return ['error' => 'El comprobante no tiene ítems para acreditar.'];
        }

        $originalByLine = [];
        foreach ($originalLines as $line) {
            $originalByLine[$line->getLineNumber()] = $line;
        }

        if ($linesJson === null || trim($linesJson) === '') {
            $copied = [];
            foreach ($originalLines as $line) {
                $copied[] = [
                    'lineNumber' => $line->getLineNumber(),
                    'qty' => $line->getQuantity(),
                ];
            }

            return $copied;
        }

        $parsed = json_decode($linesJson, true);
        if (!is_array($parsed) || $parsed === []) {
            return ['error' => 'linesJson debe ser un JSON array, por ejemplo [{"lineNumber":1,"qty":1}].'];
        }

        $resolved = [];
        foreach ($parsed as $index => $raw) {
            if (!is_array($raw)) {
                return ['error' => sprintf('La línea %d no es un objeto.', $index + 1)];
            }
            $lineNumber = (int) ($raw['lineNumber'] ?? 0);
            $qty = (float) ($raw['qty'] ?? $raw['quantity'] ?? 0);
            if ($lineNumber < 1 || !isset($originalByLine[$lineNumber])) {
                return ['error' => sprintf('La línea %d no existe en el comprobante afectado.', $lineNumber)];
            }
            if ($qty <= 0) {
                return ['error' => sprintf('La cantidad de la línea %d debe ser mayor a 0.', $lineNumber)];
            }
            $resolved[] = [
                'lineNumber' => $lineNumber,
                'qty' => (string) $qty,
            ];
        }

        return $resolved;
    }

    private function isRejected(TaxDocument $document): bool
    {
        $status = (string) ($document->getSunatStatus() ?? '');
        $statusNum = (int) $status;

        return $statusNum >= 2000 && $statusNum < 4000
        || in_array(strtolower($status), ['rejected', 'error', 'rechazado'], true);
    }

    private function resolve(?string $id, ?string $series, ?string $number): ?TaxDocument
    {
        if ($id !== null && $id !== '') {
            $found = $this->taxDocumentRepository->find($id);

            return $found instanceof TaxDocument ? $found : null;
        }

        if ($series === null || $series === '' || $number === null || $number === '') {
            return null;
        }

        $found = $this->taxDocumentRepository->findOneBy([
            'series' => strtoupper($series),
            'number' => $number,
        ]);

        return $found instanceof TaxDocument ? $found : null;
    }
}
