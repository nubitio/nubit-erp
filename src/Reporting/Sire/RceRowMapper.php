<?php

declare(strict_types=1);

namespace App\Reporting\Sire;

use Brick\Math\BigDecimal;
use DateTimeImmutable;

/**
 * Convierte una fila de `goods_receipts` (con su proveedor) en un
 * {@see RceDocument}. Sin IGV, la base va a «adquisiciones no gravadas»
 * (campo 21); con IGV va a la pareja DG (gravadas destinadas a operaciones
 * gravadas), que es el destino por defecto.
 */
final readonly class RceRowMapper
{
    public function toDocument(SireRow $row): RceDocument
    {
        $subtotal = $row->money('subtotal');
        $igv = $row->money('tax_total');
        $taxed = $igv->isPositive();

        return new RceDocument(
            type: $row->text('supplier_doc_type'),
            series: $row->text('supplier_doc_series'),
            number: $row->text('supplier_doc_number'),
            issueDate: new DateTimeImmutable(substr($row->text('issued_at'), 0, 10)),
            supplier: SireParty::fromStored($row->nullable('supplier_doc'), $row->nullable('supplier_name')),
            taxable: $taxed ? $subtotal : BigDecimal::zero(),
            igv: $igv,
            notTaxed: $taxed ? BigDecimal::zero() : $subtotal,
            total: $row->money('grand_total'),
            currency: $row->text('currency') ?: 'PEN',
            exchangeRate: $row->nullable('exchange_rate'),
        );
    }
}
