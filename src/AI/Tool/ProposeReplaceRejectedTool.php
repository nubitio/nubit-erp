<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\Entity\AssistantProposedAction;
use App\AI\Service\ProposedActionFactory;
use App\Billing\Entity\TaxDocument;
use App\Billing\Repository\TaxDocumentRepository;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'propose_replace_rejected',
    description: 'Propose replacing a SUNAT-rejected tax document with a new one. Does NOT emit anything. '
    . 'Pass taxDocumentId, or series+number. The UI shows Confirmar. Never suggest bypassing SUNAT.',
    method: 'proposeReplaceRejected',
)]
final readonly class ProposeReplaceRejectedTool
{
    public function __construct(
        private ProposedActionFactory $factory,
        private TaxDocumentRepository $taxDocumentRepository,
    ) {}

    /**
     * @return array{error: string}|array{id: string, kind: string, summary: string, payload: array<string, mixed>, expiresAt: string}
     */
    public function proposeReplaceRejected(
        ?string $taxDocumentId = null,
        ?string $series = null,
        ?string $number = null,
    ): array {
        $document = $this->resolve($taxDocumentId, $series, $number);
        if (!$document instanceof TaxDocument) {
            return ['error' => 'No se encontró el comprobante.'];
        }

        $status = (string) ($document->getSunatStatus() ?? '');
        $statusNum = (int) $status;
        $isRejected =
            $statusNum >= 2000 && $statusNum < 4000
            || in_array(strtolower($status), ['rejected', 'error', 'rechazado'], true);

        if (!$isRejected) {
            return ['error' => sprintf(
                'El comprobante %s-%s no está rechazado (estado SUNAT: %s). No propongo reemplazarlo.',
                $document->getSeries(),
                $document->getNumber(),
                $status === '' ? 'desconocido' : $status,
            )];
        }

        $saleId = $document->getCanonicalSaleId();
        if ($saleId === null || $saleId === '') {
            return ['error' => 'Este comprobante no está ligado a una venta; no se puede reemitir desde el asistente.'];
        }

        $summary = sprintf(
            'Reemplazar comprobante rechazado %s-%s (SUNAT %s)',
            $document->getSeries(),
            $document->getNumber(),
            $status,
        );

        $action = $this->factory->propose(AssistantProposedAction::KIND_REPLACE_REJECTED, $summary, [
            'saleId' => $saleId,
            'taxDocumentId' => (string) $document->getId(),
            'series' => $document->getSeries(),
            'number' => $document->getNumber(),
            'sunatStatus' => $status,
            'sunatMessage' => $document->getSunatMessage(),
        ]);

        return $action->toCard();
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
