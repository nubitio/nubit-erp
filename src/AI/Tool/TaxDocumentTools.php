<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\Billing\Entity\TaxDocument;
use App\Billing\Repository\TaxDocumentRepository;
use DateTimeInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'tax_document_status',
    description: 'Look up a SUNAT tax document (boleta, factura, nota) by id, or by series+number. '
    . 'Returns type, totals, issue date, and SUNAT status.',
    method: 'taxDocumentStatus',
)]
final readonly class TaxDocumentTools
{
    public function __construct(
        private TaxDocumentRepository $taxDocumentRepository,
    ) {}

    /**
     * @return array{error: string}|array{
     *     id: string,
     *     document_type: string,
     *     series: string,
     *     number: string,
     *     issue_date: string,
     *     total: string,
     *     sunat_status: string|null,
     *     sunat_message: string|null,
     *     sale_id: string|null
     * }
     */
    public function taxDocumentStatus(?string $id = null, ?string $series = null, ?string $number = null): array
    {
        $document = $this->resolve($id, $series, $number);
        if (!$document instanceof TaxDocument) {
            return ['error' => 'No se encontró el comprobante.'];
        }

        return [
            'id' => (string) $document->getId(),
            'document_type' => $document->getDocumentType(),
            'series' => $document->getSeries(),
            'number' => $document->getNumber(),
            'issue_date' => $document->getIssueDate()->format(DateTimeInterface::ATOM),
            'total' => $document->getTotal(),
            'sunat_status' => $document->getSunatStatus(),
            'sunat_message' => $document->getSunatMessage(),
            'sale_id' => $document->getCanonicalSaleId(),
        ];
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
