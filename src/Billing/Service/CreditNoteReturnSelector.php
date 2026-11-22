<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Billing\Entity\TaxDocument;
use App\Billing\Entity\TaxDocumentLine;
use App\Billing\Repository\TaxDocumentRepository;
use Nubit\Platform\Exception\ServiceException;

/**
 * Validates what a credit note asks to return against the affected document: the lines
 * exist, and the quantities (cumulatively with earlier notes) never exceed what was sold.
 */
readonly class CreditNoteReturnSelector
{
    public function __construct(
        private TaxDocumentRepository $taxDocumentRepository,
    ) {}

    /**
     * Detaches the requested lines from `$data` and returns them paired with their originals.
     *
     * @return list<array{original: TaxDocumentLine, quantity: float, restock: bool}>
     */
    public function select(TaxDocument $data, TaxDocument $related): array
    {
        return $this->selectLines($data, $related, $this->takeRequestedLines($data), $this->originalsByLine($related));
    }

    /**
     * Detaches the lines the caller asked for, merging repeats of a line number, and returns the request.
     *
     * @return list<array{lineNumber: int, quantity: float, restock: bool}>
     */
    private function takeRequestedLines(TaxDocument $data): array
    {
        $requested = [];
        foreach ($data->getLines() as $line) {
            $number = $line->getLineNumber();
            $requested[$number] ??= ['lineNumber' => $number, 'quantity' => 0.0, 'restock' => true];
            $requested[$number]['quantity'] += (float) $line->getQuantity();
            $requested[$number]['restock'] = $line->isRestock();
        }

        foreach ($data->getLines()->toArray() as $line) {
            $data->removeLine($line);
        }

        if ($requested === []) {
            throw new ServiceException('Debe seleccionar al menos un ítem para la nota de crédito.');
        }

        return array_values($requested);
    }

    /**
     * @return array<int, TaxDocumentLine>
     */
    private function originalsByLine(TaxDocument $related): array
    {
        if ($related->getLines()->isEmpty()) {
            throw new ServiceException('El comprobante afectado no tiene ítems registrados.');
        }

        $byLine = [];
        foreach ($related->getLines() as $line) {
            $byLine[$line->getLineNumber()] = $line;
        }

        return $byLine;
    }

    /**
     * @param list<array{lineNumber: int, quantity: float, restock: bool}> $requested
     * @param array<int, TaxDocumentLine> $originals
     * @return list<array{original: TaxDocumentLine, quantity: float, restock: bool}>
     */
    private function selectLines(TaxDocument $data, TaxDocument $related, array $requested, array $originals): array
    {
        $tracksReturns = in_array($data->getReasonCode(), ['01', '02', '06', '07'], true);
        $selected = [];
        foreach ($requested as $request) {
            if ($request['quantity'] <= 0) {
                continue;
            }
            $original = $originals[$request['lineNumber']] ?? throw new ServiceException(
                'El ítem seleccionado no existe en el comprobante afectado.',
            );
            $alreadyReturned = $tracksReturns
                ? $this->taxDocumentRepository->getReturnedQuantity($related, $request['lineNumber'])
                : 0.0;
            if (($request['quantity'] + $alreadyReturned) > (float) $original->getQuantity()) {
                throw new ServiceException('La cantidad a devolver no puede exceder la cantidad del comprobante.');
            }
            $selected[] = [
                'original' => $original,
                'quantity' => $request['quantity'],
                'restock' => $request['restock'],
            ];
        }

        if ($selected === []) {
            throw new ServiceException('Debe seleccionar al menos un ítem para la nota de crédito.');
        }

        return $selected;
    }
}
