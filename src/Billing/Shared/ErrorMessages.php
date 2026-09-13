<?php

declare(strict_types=1);

namespace App\Billing\Shared;

final class ErrorMessages
{
    public const SALE_ALREADY_HAS_VOUCHER = 'La venta ya tiene un comprobante emitido.';
    public const SALE_HAS_NO_VALID_DATE = 'La venta no tiene fecha válida.';
    public const SALE_DATE_EXCEEDS_LIMIT = 'La fecha de la venta excede el límite permitido para emisión (3 días).';
    public const NO_SERIES_CONFIGURED = 'No hay serie configurada para el tipo de comprobante.';
    public const NRUS_CANNOT_ISSUE_FACTURA = 'El negocio está acogido al Nuevo RUS y no puede emitir facturas, solo boletas.';
    public const VOUCHER_TYPE_REQUIRES_RUC = 'Para emitir factura el cliente debe tener RUC (11 dígitos).';
    public const INVOICE_NOT_FOUND = 'Invoice not found'; // Used in SaleVoidUseCase
    public const TAX_DOCUMENT_NOT_FOUND = 'Comprobante no encontrado'; // Used in SaleVoidUseCase
    public const SALE_NOT_EXISTS = 'La venta no existe.'; // Used in SaleVoidUseCase
    public const UNSUPPORTED_VOUCHER_TYPE_FOR_VOID = 'Tipo de comprobante no soportado para anulación.';
    public const VOID_SUCCESSFUL = 'Anulado correctamente';
    public const VOID_ERROR = 'Error al anular';
}
