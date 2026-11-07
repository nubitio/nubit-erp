<?php

declare(strict_types=1);

namespace App\Reporting\Sire;

/**
 * Decide qué compras no se pueden informar al RCE y qué hay que revisar en las
 * demás. No corrige nada.
 */
final readonly class RceReviewer
{
    /** Diferencia tolerada entre la suma de componentes y el total (redondeos). */
    private const string TOLERANCE = '0.05';

    /** Boleta y recibo por honorarios no dan crédito fiscal. */
    private const array NO_FISCAL_CREDIT = ['02', '03'];

    /**
     * Motivo por el que la compra no puede ir en el archivo, o null si va.
     *
     * Las notas necesitan el comprobante que modifican y la recepción no lo guarda.
     */
    public function exclusionReason(SireRow $row): ?string
    {
        $type = $row->text('supplier_doc_type');

        return match (true) {
            in_array($type, ['', '00'], true)
                || $row->text('supplier_doc_series') === ''
                || $row->text('supplier_doc_number') === ''
                => 'Sin comprobante del proveedor',
            in_array($type, ['07', '08'], true) => 'Nota sin el comprobante que modifica',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public function review(RceDocument $doc): array
    {
        $warnings = [];

        $sum = $doc->taxable->plus($doc->igv)->plus($doc->notTaxed);
        if ($sum->minus($doc->total)->abs()->isGreaterThan(self::TOLERANCE)) {
            $warnings[] = sprintf(
                'los componentes suman %s y el total es %s.',
                (string) $sum->toScale(2),
                (string) $doc->total->toScale(2),
            );
        }

        if ($doc->type === '01' && $doc->supplier->docType !== '6') {
            $warnings[] = 'una factura requiere proveedor con RUC.';
        }

        if ($doc->igv->isPositive() && in_array($doc->type, self::NO_FISCAL_CREDIT, true)) {
            $warnings[] = 'este tipo de comprobante no da crédito fiscal; revisa si el IGV debe ir con la base.';
        }

        return $warnings;
    }
}
