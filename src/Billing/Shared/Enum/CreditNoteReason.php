<?php

declare(strict_types=1);

namespace App\Billing\Shared\Enum;

/**
 * SUNAT Catálogo 09 — Tipo de Nota de Crédito Electrónica.
 *
 * El código viaja en el XML (cbc:ResponseCode) y determina el motivo de la
 * nota de crédito. Algunos motivos (01, 02, 06) representan una anulación
 * total del comprobante afectado y disparan el marcado de la venta como
 * VOIDED en {@see \App\Billing\State\TaxDocumentProcessor}.
 */
enum CreditNoteReason: string
{
    case ANULACION_OPERACION = '01';
    case ANULACION_ERROR_RUC = '02';
    case CORRECCION_DESCRIPCION = '03';
    case DESCUENTO_GLOBAL = '04';
    case DESCUENTO_ITEM = '05';
    case DEVOLUCION_TOTAL = '06';
    case DEVOLUCION_ITEM = '07';
    case BONIFICACION = '08';
    case DISMINUCION_VALOR = '09';
    case OTROS_CONCEPTOS = '10';
    case AJUSTE_EXPORTACION = '11';
    case AJUSTE_IVAP = '12';

    /**
     * Descripción oficial del motivo (cbc:Description en el XML).
     */
    public function description(): string
    {
        return match ($this) {
            self::ANULACION_OPERACION => 'Anulación de la operación',
            self::ANULACION_ERROR_RUC => 'Anulación por error en el RUC',
            self::CORRECCION_DESCRIPCION => 'Corrección por error en la descripción',
            self::DESCUENTO_GLOBAL => 'Descuento global',
            self::DESCUENTO_ITEM => 'Descuento por ítem',
            self::DEVOLUCION_TOTAL => 'Devolución total',
            self::DEVOLUCION_ITEM => 'Devolución por ítem',
            self::BONIFICACION => 'Bonificación',
            self::DISMINUCION_VALOR => 'Disminución en el valor',
            self::OTROS_CONCEPTOS => 'Otros conceptos',
            self::AJUSTE_EXPORTACION => 'Ajustes de operaciones de exportación',
            self::AJUSTE_IVAP => 'Ajustes afectos al IVAP',
        };
    }

    /**
     * Motivos que anulan por completo el comprobante afectado. Cuando una
     * nota de crédito usa uno de estos códigos la venta vinculada se marca
     * como anulada (equivalente a una Comunicación de Baja).
     */
    public function annulsDocument(): bool
    {
        return in_array(
            $this,
            [
                self::ANULACION_OPERACION,
                self::ANULACION_ERROR_RUC,
                self::DEVOLUCION_TOTAL,
            ],
            true,
        );
    }

    /**
     * @return list<array{code: string, description: string, annuls: bool}>
     */
    public static function options(): array
    {
        return array_map(static fn(self $reason): array => [
            'code' => $reason->value,
            'description' => $reason->description(),
            'annuls' => $reason->annulsDocument(),
        ], self::cases());
    }
}
