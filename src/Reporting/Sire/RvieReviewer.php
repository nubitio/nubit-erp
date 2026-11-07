<?php

declare(strict_types=1);

namespace App\Reporting\Sire;

/**
 * Problemas que hay que revisar antes de presentar el RVIE. No corrige nada:
 * lo que no cuadra o falta se devuelve como advertencia.
 */
final readonly class RvieReviewer
{
    /** Diferencia tolerada entre la suma de componentes y el total (redondeos). */
    private const string TOLERANCE = '0.05';

    /**
     * @return list<string>
     */
    public function review(RvieDocument $doc, bool $hasLines): array
    {
        $warnings = [];

        $sum = $doc->amounts->componentsSum();
        if ($sum->minus($doc->amounts->total)->abs()->isGreaterThan(self::TOLERANCE)) {
            $warnings[] = sprintf(
                'los componentes suman %s y el total es %s (¿ICBPER, redondeo u operación gratuita?).',
                (string) $sum->toScale(2),
                (string) $doc->amounts->total->toScale(2),
            );
        }

        if ($doc->isNote() && !$doc->modified instanceof RvieModifiedDocument) {
            $warnings[] = 'la nota no tiene el comprobante que modifica; SUNAT lo exige.';
        }

        if ($doc->type === '01' && $doc->customer->docType !== '6') {
            $warnings[] = 'una factura requiere cliente con RUC.';
        }

        if (!$hasLines) {
            $warnings[] = 'sin líneas: la base se informó como gravada.';
        }

        return $warnings;
    }
}
